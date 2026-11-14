<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
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
use App\Modules\Operations\Enums\CommandReceiptStatus;
use App\Modules\Operations\Models\BranchCalendar;
use App\Modules\Operations\Models\CommandReceipt;
use App\Modules\Operations\Models\OutboxEvent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_br05_quote_and_acceptance_create_consistent_order_invoice_units_and_events(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, $user, $token, $branch, $services] = $this->scenario();

        $quoteResponse = $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services));
        $quoteResponse->assertCreated()
            ->assertJsonPath('data.total_rupiah', 71600)
            ->assertJsonPath('data.totals.service_subtotal_rupiah', 74000)
            ->assertJsonPath('data.totals.discount_rupiah', 7400)
            ->assertJsonPath('data.totals.delivery_fee_rupiah', 5000)
            ->assertJsonPath('data.due_at', '2026-11-04T13:00:00Z')
            ->assertJsonPath('data.lines.0.measured_quantity', '2.300')
            ->assertJsonPath('data.lines.0.billable_quantity', '3.000')
            ->assertJsonPath('data.lines.0.line_total_rupiah', 21600)
            ->assertJsonPath('data.lines.1.measured_quantity', '2.000')
            ->assertJsonPath('data.lines.1.billable_quantity', '2.000')
            ->assertJsonPath('data.lines.1.line_total_rupiah', 45000);

        $quoteId = $quoteResponse->json('data.id');
        $clientCommandId = (string) Str::uuid();
        $acceptPayload = [
            ...$this->orderPayload($branch, $services),
            'quote_id' => $quoteId,
            'client_command_id' => $clientCommandId,
        ];

        $accepted = $this->sendAccept($tenant, $token, $acceptPayload, $clientCommandId);
        $accepted->assertCreated()
            ->assertJsonPath('data.total_rupiah', 71600)
            ->assertJsonPath('data.paid_rupiah', 0)
            ->assertJsonPath('data.balance_due_rupiah', 71600)
            ->assertJsonPath('data.invoice_status', 'unpaid')
            ->assertJsonPath('data.due_at_original', '2026-11-04T13:00:00Z')
            ->assertJsonPath('data.lines.0.measured_quantity', '2.300')
            ->assertJsonPath('data.lines.0.billable_quantity', '3.000')
            ->assertJsonPath('meta.idempotent_replay', false);

        $orderId = $accepted->json('data.order_id');
        $this->assertStringStartsWith($branch->code.'-20261102-', $accepted->json('data.order_number'));
        $this->assertStringStartsWith('INV-'.$branch->code.'-20261102-', $accepted->json('data.invoice_number'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 2);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_lines', 4);
        $this->assertDatabaseCount('bags', 1);
        $this->assertDatabaseCount('bag_contents', 2);
        $this->assertDatabaseCount('order_events', 1);
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'order.accepted')->count());
        $this->assertDatabaseCount('command_receipts', 1);

        $quote = Quote::query()->where('public_id', $quoteId)->sole();
        $this->assertNotNull($quote->consumed_at);

        $order = Order::query()->where('public_id', $orderId)->sole();
        $invoice = Invoice::query()->where('order_id', $order->id)->sole();
        $this->assertSame($invoice->public_id, $accepted->json('data.invoice_id'));
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$orderId.'/receipt')
            ->assertOk()->assertJsonPath('data.invoice_id', $invoice->public_id);
        $this->assertSame(71600, $invoice->original_total_rupiah);
        $this->assertSame(71600, (int) $invoice->lines()->sum('amount_rupiah'));
        $this->assertSame(2, $order->lines()->count());
        $this->assertSame(1, $order->bags()->count());
        $this->assertSame(2, $order->bags()->sole()->contents()->count());
        $this->assertSame(1, $order->events()->count());
        $this->assertSame('accepted', $order->events()->sole()->event_type);

        $kiloanLine = $order->lines()->whereHas('service', fn ($query) => $query->where('code', 'kiloan'))->sole();
        $this->assertSame('2.300', $kiloanLine->measured_quantity);
        $this->assertSame('3.000', $kiloanLine->billable_quantity);
        $this->assertSame(21600, $kiloanLine->line_total_rupiah);

        $receipt = CommandReceipt::query()->sole();
        $this->assertSame(CommandReceiptStatus::Completed, $receipt->status);
        $this->assertSame('order', $receipt->result_resource_type);
        $this->assertSame($orderId, $receipt->result_public_id);

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OrderAccepted->value)->count());
        $this->assertSame($user->id, AuditLog::query()->where('action', AuditAction::OrderAccepted->value)->sole()->actor_user_id);
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'order.accepted')->count());
    }

    public function test_replay_after_commit_returns_the_same_order_without_rechecking_quote_expiry(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $token, $branch, $services] = $this->scenario();
        $quoteId = $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services))->json('data.id');
        $clientCommandId = (string) Str::uuid();
        $payload = [
            ...$this->orderPayload($branch, $services),
            'quote_id' => $quoteId,
            'client_command_id' => $clientCommandId,
        ];

        $first = $this->sendAccept($tenant, $token, $payload, $clientCommandId)->assertCreated();
        $firstOrderId = $first->json('data.order_id');

        $this->travelTo('2026-11-02 08:00:00');

        $replay = $this->sendAccept($tenant, $token, $payload, $clientCommandId);
        $replay->assertOk()
            ->assertJsonPath('data.order_id', $firstOrderId)
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('command_receipts', 1);
        $this->assertDatabaseCount('invoice_lines', 4);
        $this->assertSame(1, Quote::query()->where('public_id', $quoteId)->sole()->order()->count());
    }

    public function test_quote_is_single_use_for_a_different_command(): void
    {
        [$tenant, , $token, $branch, $services] = $this->scenario();
        $quoteId = $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services))->json('data.id');
        $firstCommand = (string) Str::uuid();
        $payload = [
            ...$this->orderPayload($branch, $services),
            'quote_id' => $quoteId,
            'client_command_id' => $firstCommand,
        ];

        $this->sendAccept($tenant, $token, $payload, $firstCommand)->assertCreated();

        $secondCommand = (string) Str::uuid();
        $this->sendAccept($tenant, $token, [...$payload, 'client_command_id' => $secondCommand], $secondCommand)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUOTE_CONSUMED');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('command_receipts', 2);
        $this->assertSame(CommandReceiptStatus::Rejected, CommandReceipt::query()->where('client_command_id', $secondCommand)->sole()->status);
    }

    public function test_quote_payload_mismatch_and_expiry_do_not_create_orders(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $token, $branch, $services] = $this->scenario();
        $quoteId = $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services))->json('data.id');
        $mismatchCommand = (string) Str::uuid();
        $mismatchPayload = [
            ...$this->orderPayload($branch, $services),
            'service_discount_percent' => 0,
            'quote_id' => $quoteId,
            'client_command_id' => $mismatchCommand,
        ];

        $this->sendAccept($tenant, $token, $mismatchPayload, $mismatchCommand)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUOTE_PAYLOAD_MISMATCH');

        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(Quote::query()->where('public_id', $quoteId)->sole()->consumed_at);

        $this->travelTo('2026-11-02 08:00:00');
        $expiredCommand = (string) Str::uuid();
        $expiredPayload = [
            ...$this->orderPayload($branch, $services),
            'quote_id' => $quoteId,
            'client_command_id' => $expiredCommand,
        ];

        $this->sendAccept($tenant, $token, $expiredPayload, $expiredCommand)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUOTE_EXPIRED');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_endpoints_require_permission_branch_scope_and_matching_idempotency_key(): void
    {
        [$tenant, $user, $token, $branch, $services, $role] = $this->scenario();
        $otherBranch = Branch::factory()->create();

        $this->sendQuote($tenant, $token, $otherBranch, $this->orderPayload($otherBranch, $services))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['branch_id']]]);

        $quoteId = $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services))->json('data.id');
        $command = (string) Str::uuid();
        $payload = [
            ...$this->orderPayload($branch, $services),
            'quote_id' => $quoteId,
            'client_command_id' => $command,
        ];

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['client_command_id']]]);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to order viewer',
        );

        $this->sendQuote($tenant, $token, $branch, $this->orderPayload($branch, $services))
            ->assertForbidden();

        $this->sendAccept($tenant, $token, $payload, $command)
            ->assertForbidden();
    }

    private function sendQuote(Tenant $tenant, string $token, Branch $branch, array $payload): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/quotes', $payload);
    }

    private function sendAccept(Tenant $tenant, string $token, array $payload, string $clientCommandId): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $clientCommandId)
            ->postJson('/api/v1/orders', $payload);
    }

    /**
     * @param  array{kiloan: Service, bed_cover: Service}  $services
     * @return array<string, mixed>
     */
    private function orderPayload(Branch $branch, array $services): array
    {
        return [
            'branch_id' => $branch->public_id,
            'fulfillment_method' => 'delivery',
            'service_discount_percent' => 10,
            'lines' => [
                ['service_id' => $services['kiloan']->public_id, 'measured_quantity' => '2.3'],
                ['service_id' => $services['bed_cover']->public_id, 'measured_quantity' => '2'],
            ],
        ];
    }

    /**
     * @return array{Tenant, User, string, Branch, array{kiloan: Service, bed_cover: Service}, Role}
     */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'order-admin', 'name' => 'Order admin']);
        $branch = Branch::factory()->for($tenant)->create(['code' => 'JBI01']);

        $permissionIds = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::DiscountsStandard])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap order admin');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard']);
        $kiloan = Service::factory()->forWorkflow($workflow)->create([
            'code' => 'kiloan',
            'name' => 'Cuci Kering Kiloan',
            'unit' => 'kg',
            'minimum_quantity' => '3.000',
            'quantity_step' => '0.100',
            'is_active' => true,
        ]);
        $bedCover = Service::factory()->forWorkflow($workflow)->create([
            'code' => 'bed-cover',
            'name' => 'Bed Cover',
            'unit' => 'pcs',
            'minimum_quantity' => '1.000',
            'quantity_step' => '1.000',
            'is_active' => true,
        ]);

        $book = PriceBook::factory()->for($tenant)->create(['name' => 'Harga Tenant']);
        $effectiveFrom = CarbonImmutable::parse('2026-01-01 00:00:00', 'Asia/Jakarta')->utc();
        PriceEntry::factory()->forPriceBook($book, $kiloan)->create([
            'unit_price_rupiah' => 8000,
            'effective_from' => $effectiveFrom,
            'effective_until' => null,
        ]);
        PriceEntry::factory()->forPriceBook($book, $bedCover)->create([
            'unit_price_rupiah' => 25000,
            'effective_from' => $effectiveFrom,
            'effective_until' => null,
        ]);

        foreach (range(0, 6) as $weekday) {
            BranchCalendar::factory()->forBranch($branch)->create([
                'weekday' => $weekday,
                'rules' => $weekday === 0
                    ? ['is_open' => false]
                    : ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            ]);
        }
        BranchCalendar::factory()->forBranch($branch)->cutoff('15:00', '20:00')->create();
        BranchCalendar::factory()->forBranch($branch)->holiday('2026-11-05')->create();

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Order test device');

        return [
            $tenant,
            $user,
            $issued['token']->plainTextToken,
            $branch,
            ['kiloan' => $kiloan, 'bed_cover' => $bedCover],
            $role,
        ];
    }
}
