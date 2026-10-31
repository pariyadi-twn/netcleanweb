<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cash_payment_allocates_invoice_updates_receipt_and_replays_idempotently(): void
    {
        [$tenant, $token, $invoice, $session] = $this->scenario(71600);
        $command = (string) Str::uuid();

        $payment = $this->pay($tenant, $token, $invoice, $command, [
            'amount_rupiah' => 30000,
            'method' => 'cash',
            'cash_session_id' => $session->public_id,
            'cash_received_rupiah' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.invoice_status', 'partially_paid')
            ->assertJsonPath('data.total_rupiah', 71600)
            ->assertJsonPath('data.paid_rupiah', 30000)
            ->assertJsonPath('data.balance_due_rupiah', 41600)
            ->assertJsonPath('data.allocated_rupiah', 30000)
            ->assertJsonPath('data.unallocated_rupiah', 0)
            ->assertJsonPath('data.cash_received_rupiah', 50000)
            ->assertJsonPath('data.change_rupiah', 20000)
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('cash_entries', [
            'tenant_id' => $tenant->id,
            'kind' => 'payment_in',
            'amount_rupiah' => 30000,
            'reference_type' => 'payment',
        ]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'payment.recorded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.recorded']);

        $this->pay($tenant, $token, $invoice, $command, [
            'amount_rupiah' => 30000,
            'method' => 'cash',
            'cash_session_id' => $session->public_id,
            'cash_received_rupiah' => 50000,
        ])->assertOk()
            ->assertJsonPath('data.payment_id', $payment->json('data.payment_id'))
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertDatabaseCount('payments', 1);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$invoice->order->public_id.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.totals.total_rupiah', 71600)
            ->assertJsonPath('data.totals.paid_rupiah', 30000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 41600);

        $overpayment = $this->pay($tenant, $token, $invoice, (string) Str::uuid(), [
            'amount_rupiah' => 50000,
            'method' => 'cash',
            'cash_session_id' => $session->public_id,
            'cash_received_rupiah' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.invoice_status', 'paid')
            ->assertJsonPath('data.paid_rupiah', 71600)
            ->assertJsonPath('data.balance_due_rupiah', 0)
            ->assertJsonPath('data.allocated_rupiah', 41600)
            ->assertJsonPath('data.unallocated_rupiah', 8400);

        $this->assertGreaterThan(0, $overpayment->json('data.payment_id') !== null);
    }

    public function test_bank_payment_and_credit_approval_are_audited_and_tenant_scoped(): void
    {
        [$tenant, $token, $invoice] = $this->scenario(71600);

        $this->pay($tenant, $token, $invoice, (string) Str::uuid(), [
            'amount_rupiah' => 10000,
            'method' => 'bank_manual',
        ])->assertCreated()
            ->assertJsonPath('data.paid_rupiah', 10000)
            ->assertJsonPath('data.change_rupiah', 0)
            ->assertJsonPath('data.unallocated_rupiah', 0);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/invoices/'.$invoice->public_id.'/credit-approval', [
                'limit_rupiah' => 61600,
                'expires_at' => now()->addDays(7)->toIso8601String(),
                'reason' => 'Pelanggan langganan',
            ])
            ->assertOk()
            ->assertJsonPath('data.invoice_id', $invoice->public_id)
            ->assertJsonPath('data.limit_rupiah', 61600);

        $this->assertDatabaseHas('credit_approvals', ['tenant_id' => $tenant->id, 'limit_rupiah' => 61600]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credit.approved']);

        $other = Invoice::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/invoices/'.$other->public_id.'/credit-approval', [
                'limit_rupiah' => 1,
                'reason' => 'Cross tenant',
            ])
            ->assertNotFound();
    }

    public function test_payment_requires_permission_and_open_cash_session(): void
    {
        [$tenant, $token, $invoice, , $role, $user] = $this->scenario(71600);

        $this->pay($tenant, $token, $invoice, (string) Str::uuid(), [
            'amount_rupiah' => 30000,
            'method' => 'cash',
            'cash_session_id' => (string) Str::uuid(),
            'cash_received_rupiah' => 50000,
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['cash_session_id']]]);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to order viewer',
        );

        $this->pay($tenant, $token, $invoice, (string) Str::uuid(), [
            'amount_rupiah' => 30000,
            'method' => 'bank_manual',
        ])->assertForbidden();
    }

    private function pay(Tenant $tenant, string $token, Invoice $invoice, string $command, array $payload)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/invoices/'.$invoice->public_id.'/payments', [
                'client_command_id' => $command,
                ...$payload,
            ]);
    }

    /** @return array{Tenant, string, Invoice, CashSession, Role, User} */
    private function scenario(int $total): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([
            PermissionCode::OrdersView,
            PermissionCode::PaymentsView,
            PermissionCode::PaymentsManage,
            PermissionCode::CreditManage,
        ])->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
            ['code' => $code->value],
            ['description' => $code->name],
        )->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cashier');

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'sales_branch_id' => $branch->id,
        ]);
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
            'original_total_rupiah' => $total,
        ]);

        $register = CashRegister::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
        ]);
        $session = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $register->id,
            'cashier_membership_id' => $membership->id,
            'status' => 'open',
        ]);

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Payment device')['token']->plainTextToken;

        return [$tenant, $token, $invoice, $session, $role, $user];
    }
}
