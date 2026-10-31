<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cancellation_keeps_payment_bag_and_invoice_traceable_and_blocks_new_payment(): void
    {
        [$tenant, $token, $order, $invoice, $bag] = $this->scenario(OrderStatus::Accepted, 30000);
        $command = (string) Str::uuid();

        $response = $this->cancel($tenant, $token, $order, $command, $order->version)
            ->assertCreated()
            ->assertJsonPath('data.operational_status', 'cancelled')
            ->assertJsonPath('data.invoice_status', 'issued')
            ->assertJsonPath('data.total_rupiah', 71600)
            ->assertJsonPath('data.paid_rupiah', 30000)
            ->assertJsonPath('data.balance_due_rupiah', 41600)
            ->assertJsonPath('data.refund_required_rupiah', 30000)
            ->assertJsonPath('data.bags.0.bag_id', $bag->public_id)
            ->assertJsonPath('data.bags.0.custody_status', 'at_location')
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseHas('orders', ['tenant_id' => $tenant->id, 'id' => $order->id, 'operational_status' => 'cancelled']);
        $this->assertDatabaseHas('order_events', ['tenant_id' => $tenant->id, 'order_id' => $order->id, 'event_type' => 'cancelled']);
        $this->assertDatabaseHas('bags', ['tenant_id' => $tenant->id, 'id' => $bag->id, 'custody_status' => 'at_location']);
        $this->assertDatabaseHas('payments', ['tenant_id' => $tenant->id, 'amount_rupiah' => 30000]);
        $this->assertDatabaseCount('refunds', 0);

        $this->cancel($tenant, $token, $order, $command, $order->version)
            ->assertOk()
            ->assertJsonPath('data.order_id', $response->json('data.order_id'))
            ->assertJsonPath('meta.idempotent_replay', true);

        $secondCommand = (string) Str::uuid();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $secondCommand)
            ->postJson('/api/v1/invoices/'.$invoice->public_id.'/payments', [
                'client_command_id' => $secondCommand,
                'amount_rupiah' => 10000,
                'method' => 'bank_manual',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_CANCELLED');
    }

    public function test_ready_order_cancellation_requires_approval_and_handed_over_goods_are_rejected(): void
    {
        [$tenant, $token, $ready, , , $role, $user] = $this->scenario(OrderStatus::Ready, 0);

        $this->cancel($tenant, $token, $ready, (string) Str::uuid(), $ready->version)
            ->assertForbidden();

        $permissionIds = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::CancellationsApprove])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        app(SyncRolePermissions::class)->handle($role, $permissionIds->all(), $user, 'Add cancellation approval');

        $this->cancel($tenant, $token, $ready, (string) Str::uuid(), $ready->version)
            ->assertCreated()
            ->assertJsonPath('data.operational_status', 'cancelled');

        [$tenant2, $token2, $handedOver] = $this->scenario(OrderStatus::Accepted, 0);
        $otherBag = Bag::factory()->create([
            'tenant_id' => $tenant2->id,
            'order_id' => $handedOver->id,
            'custody_status' => 'handed_over',
        ]);

        $this->cancel($tenant2, $token2, $handedOver, (string) Str::uuid(), $handedOver->version)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_GOODS_HANDED_OVER');

        $this->assertDatabaseHas('orders', ['id' => $handedOver->id, 'operational_status' => 'accepted']);
        $this->assertDatabaseHas('bags', ['id' => $otherBag->id, 'custody_status' => 'handed_over']);
    }

    public function test_version_conflict_and_tenant_scope_are_rejected(): void
    {
        [$tenant, $token, $order] = $this->scenario(OrderStatus::Accepted, 0);

        $this->cancel($tenant, $token, $order, (string) Str::uuid(), $order->version + 1)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_VERSION_CONFLICT');

        $other = Order::factory()->create();
        $this->cancel($tenant, $token, $other, (string) Str::uuid(), 1)->assertNotFound();
    }

    private function cancel(Tenant $tenant, string $token, Order $order, string $command, int $version)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/orders/'.$order->public_id.'/cancel', [
                'client_command_id' => $command,
                'expected_version' => $version,
                'reason' => 'Pelanggan membatalkan pesanan',
            ]);
    }

    /** @return array{Tenant, string, Order, Invoice, Bag, Role, User} */
    private function scenario(OrderStatus $status, int $paid): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'order-admin', 'name' => 'Order admin']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::PaymentsManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap order admin');

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'operational_status' => $status,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'original_total_rupiah' => 71600,
        ]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'custody_status' => 'at_location']);

        if ($paid > 0) {
            $payment = new Payment([
                'received_branch_id' => $branch->id,
                'method' => 'bank_manual',
                'amount_rupiah' => $paid,
                'currency' => 'IDR',
                'cash_received_rupiah' => $paid,
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
                'amount_rupiah' => $paid,
            ]);
            $allocation->tenant_id = $tenant->id;
            $allocation->save();
        }

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Cancellation device')['token']->plainTextToken;

        return [$tenant, $token, $order, $invoice, $bag, $role, $user];
    }
}
