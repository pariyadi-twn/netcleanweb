<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BagRepackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_label_and_list_expose_unique_bag_without_customer_pii(): void
    {
        [$tenant, $token, $order, $bag, $line] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/bags')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bag->public_id)
            ->assertJsonPath('data.0.label_code', 'BAG-SOURCE')
            ->assertJsonPath('data.0.contents.0.physical_quantity', '3.000');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/bags/'.$bag->public_id.'/label')
            ->assertOk()
            ->assertJsonPath('data.label_code', 'BAG-SOURCE')
            ->assertJsonPath('data.qr_payload', 'BAG-SOURCE')
            ->assertJsonPath('data.order_number', 'BAG-ORDER-1')
            ->assertJsonMissingPath('data.customer')
            ->assertJsonMissingPath('data.total_rupiah');

        $this->assertSame($line->public_id, $bag->contents()->sole()->orderLine->public_id);
    }

    public function test_repack_preserves_line_total_and_creates_unique_target_label(): void
    {
        [$tenant, $token, $order, $bag, $line] = $this->scenario();

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/repack', [
                'source_bag_id' => $bag->public_id,
                'reason' => 'Split for production',
                'moves' => [
                    ['order_line_id' => $line->public_id, 'quantity' => '1.0'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.source.contents.0.physical_quantity', '2.000')
            ->assertJsonPath('data.target.contents.0.physical_quantity', '1.000');

        $targetId = $response->json('data.target.id');
        $this->assertNotSame($bag->label_code, $response->json('data.target.label_code'));
        $this->assertSame(2, Bag::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseCount('bag_repack_events', 1);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::BagRepacked->value)->count());

        $target = Bag::query()->where('public_id', $targetId)->sole();
        $this->assertSame($order->id, $target->order_id);
        $this->assertEquals(3.0, (float) BagContent::query()
            ->where('order_id', $order->id)
            ->where('order_line_id', $line->id)
            ->sum('physical_quantity'));
    }

    public function test_repack_rejects_excess_quantity_and_cross_order_target(): void
    {
        [$tenant, $token, $order, $bag, $line] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/repack', [
                'source_bag_id' => $bag->public_id,
                'reason' => 'Invalid move',
                'moves' => [
                    ['order_line_id' => $line->public_id, 'quantity' => '4.0'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['moves']]]);

        $this->assertSame('3.000', $bag->contents()->sole()->physical_quantity);

        $otherOrder = Order::factory()->create();
        $otherBag = Bag::factory()->create(['order_id' => $otherOrder->id]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/repack', [
                'source_bag_id' => $bag->public_id,
                'target_bag_id' => $otherBag->public_id,
                'reason' => 'Cross order',
                'moves' => [
                    ['order_line_id' => $line->public_id, 'quantity' => '1.0'],
                ],
            ])
            ->assertNotFound();
    }

    public function test_viewer_cannot_repack_but_can_read_bags(): void
    {
        [$tenant, $token, $order, $bag, $line, $role, $user] = $this->scenario();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to order viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/bags')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/repack', [
                'source_bag_id' => $bag->public_id,
                'reason' => 'Denied',
                'moves' => [
                    ['order_line_id' => $line->public_id, 'quantity' => '1.0'],
                ],
            ])
            ->assertForbidden();
    }

    /** @return array{Tenant, string, Order, Bag, OrderLine, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cashier');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Kiloan']);
        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('b', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 24000,
            'pricing_snapshot' => [],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();
        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'BAG-ORDER-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'PII Customer'],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();
        $invoice = new Invoice([
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'invoice_number' => 'INV-BAG-1',
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => 24000,
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);
        $invoice->tenant_id = $tenant->id;
        $invoice->save();
        $line = new OrderLine([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'unit' => 'kg',
            'measured_quantity' => '3.000',
            'billable_quantity' => '3.000',
            'unit_price_rupiah' => 8000,
            'line_total_rupiah' => 24000,
            'service_snapshot' => ['name' => 'Kiloan'],
            'status' => 'active',
        ]);
        $line->tenant_id = $tenant->id;
        $line->save();
        $bag = new Bag([
            'order_id' => $order->id,
            'label_code' => 'BAG-SOURCE',
            'custody_status' => 'at_location',
        ]);
        $bag->tenant_id = $tenant->id;
        $bag->save();
        $content = new BagContent([
            'order_id' => $order->id,
            'bag_id' => $bag->id,
            'order_line_id' => $line->id,
            'physical_quantity' => '3.000',
            'handed_over_quantity' => '0.000',
            'is_active' => true,
        ]);
        $content->tenant_id = $tenant->id;
        $content->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Bag device')['token']->plainTextToken;

        return [$tenant, $token, $order, $bag, $line, $role, $user];
    }
}
