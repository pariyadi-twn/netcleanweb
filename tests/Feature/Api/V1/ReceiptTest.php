<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Actions\IssueTrackingToken;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_digital_receipt_is_built_from_stored_order_invoice_and_tracking(): void
    {
        [$tenant, $token, $order, $membership, $role, $user] = $this->scenario();
        $tracking = app(IssueTrackingToken::class)->handle($order, $membership->id)['token'];

        $withoutTracking = $this->receipt($tenant, $token, $order)->assertOk()
            ->assertJsonPath('data.order_number', 'RCPT-1')
            ->assertJsonPath('data.invoice_number', 'INV-RCPT-1')
            ->assertJsonPath('data.totals.service_subtotal_rupiah', 74000)
            ->assertJsonPath('data.totals.discount_rupiah', 7400)
            ->assertJsonPath('data.totals.delivery_fee_rupiah', 5000)
            ->assertJsonPath('data.totals.total_rupiah', 71600)
            ->assertJsonPath('data.totals.paid_rupiah', 0)
            ->assertJsonPath('data.totals.balance_due_rupiah', 71600)
            ->assertJsonPath('data.lines.0.measured_quantity', '2.300')
            ->assertJsonPath('data.lines.0.billable_quantity', '3.000')
            ->assertJsonPath('data.tracking_url', null);

        $withTracking = $this->receipt($tenant, $token, $order, $tracking)->assertOk()
            ->assertJsonPath('data.tracking_url', url('/api/v1/tracking/'.$tracking));

        $this->assertSame($withoutTracking->json('data.receipt_hash'), $withTracking->json('data.receipt_hash'));

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to receipt viewer',
        );

        $this->receipt($tenant, $token, $order)->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/receipt-prints', ['outcome' => 'printed'])
            ->assertForbidden();
    }

    public function test_print_attempts_are_separate_from_order_and_same_receipt_hash(): void
    {
        [$tenant, $token, $order] = $this->scenario();
        $receiptHash = $this->receipt($tenant, $token, $order)->json('data.receipt_hash');

        $printed = $this->print($tenant, $token, $order, ['outcome' => 'printed'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'printed')
            ->assertJsonPath('data.receipt_hash', $receiptHash);

        $this->print($tenant, $token, $order, ['outcome' => 'failed', 'error_code' => 'PAPER_OUT'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'PAPER_OUT');

        $this->assertDatabaseCount('receipt_print_attempts', 2);
        $this->assertDatabaseCount('orders', 1);
        $this->assertNotNull($printed->json('data.id'));
    }

    public function test_invalid_tracking_token_and_cross_tenant_order_are_rejected(): void
    {
        [$tenant, $token, $order] = $this->scenario();
        $other = Order::factory()->create();

        $this->receipt($tenant, $token, $order, str_repeat('x', 43))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['tracking_token']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$other->public_id.'/receipt')
            ->assertNotFound();
    }

    private function receipt(Tenant $tenant, string $token, Order $order, ?string $tracking = null)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/receipt', [
                'tracking_token' => $tracking,
            ]);
    }

    private function print(Tenant $tenant, string $token, Order $order, array $payload)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/receipt-prints', $payload);
    }

    /** @return array{Tenant, string, Order, TenantMembership, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create(['name' => 'Cabang Nota', 'phone' => '081211111111']);
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cashier');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $kiloan = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Kiloan']);
        $bed = Service::factory()->forWorkflow($workflow)->create(['code' => 'bed-cover', 'name' => 'Bed Cover', 'unit' => 'pcs', 'minimum_quantity' => '1.000', 'quantity_step' => '1.000']);

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'delivery',
            'pickup_branch_id' => null,
            'request_hash' => str_repeat('c', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 71600,
            'pricing_snapshot' => ['totals' => ['delivery_fee_rupiah' => 5000]],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();

        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'RCPT-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'delivery',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => [
                'name' => 'Siti',
                'normalized_phone' => '081234567890',
                'address' => 'Jl. Merdeka 1',
            ],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        $fields = [
            ['service' => $kiloan, 'measured' => '2.300', 'billable' => '3.000', 'price' => 8000, 'base' => 24000, 'discount' => 2400, 'surcharge' => 0, 'total' => 21600],
            ['service' => $bed, 'measured' => '2.000', 'billable' => '2.000', 'price' => 25000, 'base' => 50000, 'discount' => 5000, 'surcharge' => 0, 'total' => 45000],
        ];
        foreach ($fields as $field) {
            $line = new OrderLine([
                'order_id' => $order->id,
                'service_id' => $field['service']->id,
                'unit' => $field['service']->unit->value,
                'measured_quantity' => $field['measured'],
                'billable_quantity' => $field['billable'],
                'unit_price_rupiah' => $field['price'],
                'line_total_rupiah' => $field['total'],
                'service_snapshot' => [
                    'name' => $field['service']->name,
                    'base_total_rupiah' => $field['base'],
                    'discount_rupiah' => $field['discount'],
                    'express_surcharge_rupiah' => $field['surcharge'],
                    'line_total_rupiah' => $field['total'],
                ],
                'status' => 'active',
            ]);
            $line->tenant_id = $tenant->id;
            $line->save();
        }

        $invoice = new Invoice([
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'invoice_number' => 'INV-RCPT-1',
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => 71600,
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);
        $invoice->tenant_id = $tenant->id;
        $invoice->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Receipt device')['token']->plainTextToken;

        return [$tenant, $token, $order, $membership, $role, $user];
    }
}
