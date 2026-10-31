<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
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
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderRevisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_small_quantity_revision_applies_automatically_with_debit_note(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $requesterToken, , $order, $line] = $this->scenario();

        $revision = $this->revise($tenant, $requesterToken, $order, 1, [
            ['order_line_id' => $line->public_id, 'measured_quantity' => '3.1'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.difference_rupiah', 800)
            ->assertJsonPath('data.new_total_rupiah', 24800);

        $this->assertNotNull($revision->json('data.invoice_adjustment_id'));
        $line->refresh();
        $this->assertSame('3.100', $line->measured_quantity);
        $this->assertSame('3.100', $line->billable_quantity);
        $this->assertSame(24800, $line->line_total_rupiah);
        $this->assertSame(2, $order->refresh()->version);
        $this->assertDatabaseHas('invoice_adjustments', [
            'tenant_id' => $tenant->id,
            'kind' => 'debit_note',
            'amount_rupiah' => 800,
        ]);
    }

    public function test_large_revision_waits_for_a_different_override_actor(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $requesterToken, $approverToken, $order, $line] = $this->scenario();

        $revision = $this->revise($tenant, $requesterToken, $order, 1, [
            ['order_line_id' => $line->public_id, 'measured_quantity' => '20'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.difference_rupiah', 136000);

        $this->assertDatabaseMissing('invoice_adjustments', ['tenant_id' => $tenant->id]);
        $this->assertSame('3.000', $line->refresh()->measured_quantity);

        $revisionId = $revision->json('data.id');
        $this->decide($tenant, $requesterToken, $order, $revisionId, 'approved')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->decide($tenant, $approverToken, $order, $revisionId, 'approved')
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.difference_rupiah', 136000);

        $line->refresh();
        $this->assertSame('20.000', $line->measured_quantity);
        $this->assertSame(160000, $line->line_total_rupiah);
        $this->assertDatabaseHas('invoice_adjustments', [
            'tenant_id' => $tenant->id,
            'kind' => 'debit_note',
            'amount_rupiah' => 136000,
        ]);
    }

    public function test_revision_rejects_stale_version_and_no_op_changes(): void
    {
        [$tenant, , $requesterToken, , $order, $line] = $this->scenario();

        $this->revise($tenant, $requesterToken, $order, 99, [
            ['order_line_id' => $line->public_id, 'measured_quantity' => '3.1'],
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['expected_version']]]);

        $this->revise($tenant, $requesterToken, $order, 1, [
            ['order_line_id' => $line->public_id, 'measured_quantity' => '3'],
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['lines']]]);

        $this->assertDatabaseCount('order_revisions', 0);
    }

    private function revise(
        Tenant $tenant,
        string $token,
        Order $order,
        int $expectedVersion,
        array $lines,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/revisions', [
                'expected_version' => $expectedVersion,
                'reason' => 'Customer corrected the weight',
                'lines' => $lines,
            ]);
    }

    private function decide(
        Tenant $tenant,
        string $token,
        Order $order,
        string $revisionId,
        string $decision,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/revisions/'.$revisionId.'/decision', [
                'decision' => $decision,
                'reason' => 'Manager decision',
            ]);
    }

    /** @return array{Tenant, TenantMembership, string, string, Order, OrderLine} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $requesterMembership = TenantMembership::factory()->for($tenant)->for($requester)->create(['status' => 'active']);
        $approverMembership = TenantMembership::factory()->for($tenant)->for($approver)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create(['code' => 'JBI01']);
        $requesterRole = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $approverRole = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);

        $requesterPermissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        $approverPermissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::DiscountsOverride])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($requesterPermissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $requesterRole->id, 'permission_id' => $id,
        ])->all());
        DB::table('role_permissions')->insert($approverPermissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $approverRole->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($requesterMembership, [$requesterRole->id], [$branch->id], [], $requester, 'Bootstrap cashier');
        app(UpdateMembershipAssignments::class)->handle($approverMembership, [$approverRole->id], [$branch->id], [], $approver, 'Bootstrap owner');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard']);
        $service = Service::factory()->forWorkflow($workflow)->create([
            'code' => 'kiloan',
            'unit' => 'kg',
            'minimum_quantity' => '3.000',
            'quantity_step' => '0.100',
            'is_active' => true,
        ]);
        $book = PriceBook::factory()->for($tenant)->create();
        PriceEntry::factory()->forPriceBook($book, $service)->create([
            'unit_price_rupiah' => 8000,
            'effective_from' => CarbonImmutable::parse('2026-01-01 00:00:00', 'Asia/Jakarta')->utc(),
            'effective_until' => null,
        ]);

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 24000,
            'pricing_snapshot' => [
                'input' => ['service_discount_percent' => 0],
                'totals' => ['delivery_fee_rupiah' => 0],
            ],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();

        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $requesterMembership->id,
            'order_number' => 'ORD-REV-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'Walk-in'],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        $line = new OrderLine([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'unit' => 'kg',
            'measured_quantity' => '3.000',
            'billable_quantity' => '3.000',
            'unit_price_rupiah' => 8000,
            'line_total_rupiah' => 24000,
            'service_snapshot' => [
                'id' => $service->public_id,
                'name' => 'Cuci Kering Kiloan',
                'code' => 'kiloan',
                'pricing' => ['tier' => 'regular', 'unit_price_rupiah' => 8000],
                'base_total_rupiah' => 24000,
                'discount_rupiah' => 0,
                'express_surcharge_rupiah' => 0,
                'line_total_rupiah' => 24000,
            ],
            'status' => 'active',
        ]);
        $line->tenant_id = $tenant->id;
        $line->save();

        $invoice = new Invoice([
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'invoice_number' => 'INV-REV-1',
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => 24000,
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);
        $invoice->tenant_id = $tenant->id;
        $invoice->save();

        $requesterToken = app(IssueDeviceToken::class)->handle($requester, (string) Str::uuid(), 'Requester device')['token']->plainTextToken;
        $approverToken = app(IssueDeviceToken::class)->handle($approver, (string) Str::uuid(), 'Approver device')['token']->plainTextToken;

        return [$tenant, $requesterMembership, $requesterToken, $approverToken, $order, $line];
    }
}
