<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_refund_reserves_then_allocates_and_cashes_out_without_double_subtraction(): void
    {
        [$tenant, $token, $invoice, $payment, $session] = $this->scenario(100000);
        $command = (string) Str::uuid();

        $refund = $this->requestRefund($tenant, $token, $payment, $command, 20000)
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.amount_rupiah', 20000)
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 100000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 0);

        $this->requestRefund($tenant, $token, $payment, $command, 20000)
            ->assertOk()
            ->assertJsonPath('data.refund_id', $refund->json('data.refund_id'))
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->thisRefund($tenant, $token, $refund->json('data.refund_id'), 'decision', [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->thisRefund($tenant, $token, $refund->json('data.refund_id'), 'status', [
            'status' => 'succeeded',
            'cash_session_id' => $session->public_id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.allocated_refund_rupiah', 20000);

        $this->assertDatabaseHas('refund_allocations', [
            'tenant_id' => $tenant->id,
            'payment_id' => $payment->id,
            'amount_rupiah' => 20000,
        ]);
        $this->assertDatabaseHas('cash_entries', [
            'tenant_id' => $tenant->id,
            'kind' => 'refund_out',
            'amount_rupiah' => -20000,
            'reference_type' => 'refund',
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 80000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 20000);

        $secondPayment = (string) Str::uuid();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $secondPayment)
            ->postJson('/api/v1/invoices/'.$invoice->public_id.'/payments', [
                'client_command_id' => $secondPayment,
                'amount_rupiah' => 20000,
                'method' => 'bank_manual',
            ])
            ->assertCreated()
            ->assertJsonPath('data.paid_rupiah', 100000)
            ->assertJsonPath('data.balance_due_rupiah', 0)
            ->assertJsonPath('data.allocated_rupiah', 20000);

        $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 90000)
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['amount_rupiah']]]);
    }

    public function test_pending_refund_reserves_balance_and_rejection_releases_it(): void
    {
        [$tenant, $token, $invoice, $payment] = $this->scenario(100000);

        $first = $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 60000)
            ->assertCreated();

        $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 50000)
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['amount_rupiah']]]);

        $this->thisRefund($tenant, $token, $first->json('data.refund_id'), 'decision', [
            'decision' => 'rejected',
            'reason' => 'Pelanggan membatalkan',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 50000)
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 100000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 0);
    }

    public function test_reversal_is_append_only_and_stacks_with_refund_allocation(): void
    {
        [$tenant, $token, $invoice, $payment, $session, $allocation] = $this->scenario(100000);

        $this->reversal($tenant, $token, $allocation, (string) Str::uuid(), 20000)
            ->assertCreated()
            ->assertJsonPath('data.amount_rupiah', 20000);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 80000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 20000);

        $this->reversal($tenant, $token, $allocation, (string) Str::uuid(), 90000)
            ->assertUnprocessable();

        $refund = $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 20000)->assertCreated();
        $this->thisRefund($tenant, $token, $refund->json('data.refund_id'), 'decision', [
            'decision' => 'approved',
        ])->assertOk();
        $this->thisRefund($tenant, $token, $refund->json('data.refund_id'), 'status', [
            'status' => 'succeeded',
            'cash_session_id' => $session->public_id,
        ])->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 60000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 40000);
    }

    public function test_refund_above_100000_requires_a_different_approver(): void
    {
        [$tenant, $token, $invoice, $payment, $session, , $approverToken] = $this->scenario(150000);

        $refund = $this->requestRefund($tenant, $token, $payment, (string) Str::uuid(), 120000)->assertCreated();

        $this->thisRefund($tenant, $token, $refund->json('data.refund_id'), 'decision', [
            'decision' => 'approved',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'REFUND_APPROVER_CONFLICT');

        $this->thisRefund($tenant, $approverToken, $refund->json('data.refund_id'), 'decision', [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->thisRefund($tenant, $approverToken, $refund->json('data.refund_id'), 'status', [
            'status' => 'succeeded',
            'cash_session_id' => $session->public_id,
        ])->assertOk()->assertJsonPath('data.status', 'succeeded');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_rupiah', 30000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 120000);
    }

    private function requestRefund(Tenant $tenant, string $token, Payment $payment, string $command, int $amount)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/payments/'.$payment->public_id.'/refunds', [
                'client_command_id' => $command,
                'amount_rupiah' => $amount,
                'reason' => 'Pelanggan meminta pengembalian',
            ]);
    }

    private function thisRefund(Tenant $tenant, string $token, string $refundId, string $action, array $payload)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/refunds/'.$refundId.'/'.$action, $payload);
    }

    private function reversal(Tenant $tenant, string $token, PaymentAllocation $allocation, string $command, int $amount)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/payment-allocations/'.$allocation->public_id.'/reversals', [
                'client_command_id' => $command,
                'amount_rupiah' => $amount,
                'reason' => 'Koreksi alokasi',
            ]);
    }

    /** @return array{Tenant, string, Invoice, Payment, CashSession, PaymentAllocation, string} */
    private function scenario(int $amount): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $approver = User::factory()->create();
        $approverMembership = TenantMembership::factory()->for($tenant)->for($approver)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'refund-admin', 'name' => 'Refund admin']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::PaymentsManage, PermissionCode::RefundsRequest, PermissionCode::RefundsApprove])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap refund admin');
        app(UpdateMembershipAssignments::class)->handle($approverMembership, [$role->id], [$branch->id], [], $approver, 'Bootstrap refund approver');

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'original_total_rupiah' => $amount,
        ]);
        $register = CashRegister::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);
        $session = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $register->id,
            'cashier_membership_id' => $membership->id,
            'status' => 'open',
        ]);

        $payment = new Payment([
            'received_branch_id' => $branch->id,
            'cash_session_id' => $session->id,
            'method' => 'cash',
            'amount_rupiah' => $amount,
            'currency' => 'IDR',
            'cash_received_rupiah' => $amount,
            'change_rupiah' => 0,
            'unallocated_rupiah' => 0,
            'received_at' => now(),
            'received_by_membership_id' => $membership->id,
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();

        $allocation = new PaymentAllocation([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_rupiah' => $amount,
        ]);
        $allocation->tenant_id = $tenant->id;
        $allocation->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Refund device')['token']->plainTextToken;
        $approverToken = app(IssueDeviceToken::class)->handle($approver, (string) Str::uuid(), 'Approver device')['token']->plainTextToken;

        return [$tenant, $token, $invoice, $payment, $session, $allocation, $approverToken];
    }
}
