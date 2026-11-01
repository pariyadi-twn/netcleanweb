<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Cash\Actions\CloseCashSession;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Logistics\Actions\CreateHandover;
use App\Modules\Order\Actions\AcceptOrder;
use App\Modules\Order\Actions\NormalizeOrderInput;
use App\Modules\Order\Actions\RepackBag;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Actions\RecordPayment;
use App\Modules\Payment\Actions\RequestRefund;
use App\Modules\Payment\Actions\ReverseAllocation;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Production\Actions\PlanRewash;
use App\Modules\Production\Models\Complaint;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use App\Modules\Saas\Actions\ConsumeQuota;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Models\SaasPlan;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrencyLedgerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_last_quota_slot_has_exactly_one_winner_across_two_processes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $plan = SaasPlan::factory()->create([
            'code' => 'one-staff',
            'revision' => 1,
            'features' => ['limits' => ['staff' => 1], 'features' => []],
        ]);
        app(StartSubscription::class)->handle($tenant, $plan);

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(ConsumeQuota::class)->handle($tenant->id, 'staff')),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(ConsumeQuota::class)->handle($tenant->id, 'staff')),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertDatabaseHas('tenant_usage_counters', ['tenant_id' => $tenant->id, 'metric_code' => 'staff', 'used' => 1]);
    }

    public function test_two_processes_cannot_handover_the_same_bag_twice(): void
    {
        [$tenant, $user, $membership, $order, $bag, $location] = $this->handoverScenario();

        $result = $this->runConcurrently([
            fn (): mixed => $this->handover($tenant, $user, $membership, $order, $bag, $location),
            fn (): mixed => $this->handover($tenant, $user, $membership, $order, $bag, $location),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertDatabaseCount('handovers', 1);
        $this->assertDatabaseHas('bags', ['id' => $bag->id, 'custody_status' => 'handed_over']);
    }

    public function test_concurrent_refunds_reserve_the_source_payment_once(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [], $user, 'Concurrency refund');
        $payment = new Payment([
            'received_branch_id' => $branch->id,
            'method' => 'bank_manual',
            'amount_rupiah' => 100000,
            'currency' => 'IDR',
            'cash_received_rupiah' => 100000,
            'change_rupiah' => 0,
            'unallocated_rupiah' => 0,
            'received_at' => now(),
            'received_by_membership_id' => $membership->id,
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RequestRefund::class)->handle($payment, 60000, 'Bersamaan', $membership->id, $user)),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RequestRefund::class)->handle($payment, 60000, 'Bersamaan', $membership->id, $user)),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame(60000, (int) DB::table('refunds')->where('tenant_id', $tenant->id)->sum('amount_rupiah'));
    }

    public function test_quote_is_consumed_once_across_processes_with_immutable_snapshot_price(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create(['is_active' => true]);
        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard', 'is_active' => true]);
        $service = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Cuci Kiloan']);
        $input = [
            'branch_id' => $branch->public_id,
            'fulfillment_method' => 'pickup',
            'lines' => [['service_id' => $service->public_id, 'measured_quantity' => '2.3', 'tier' => 'regular']],
        ];
        $normalized = app(NormalizeOrderInput::class)->handle($input);
        $line = [
            'service_id' => $service->public_id,
            'unit' => 'kg',
            'measured_quantity' => '2.300',
            'billable_quantity' => '3.000',
            'unit_price_rupiah' => 8000,
            'line_total_rupiah' => 24000,
            'base_total_rupiah' => 24000,
            'discount_rupiah' => 0,
            'express_surcharge_rupiah' => 0,
            'pricing' => ['source' => 'tenant'],
            'service_snapshot' => ['name' => 'Cuci Kiloan', 'workflow' => ['steps' => [['code' => 'washing', 'sequence' => 1, 'is_required' => true, 'requires_qc' => false]]]],
            'due_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'request_hash' => app(NormalizeOrderInput::class)->requestHash($normalized),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 24000,
            'pricing_snapshot' => [
                'input' => ['service_discount_percent' => 0],
                'customer_snapshot' => ['name' => 'Walk-in'],
                'lines' => [$line],
                'totals' => ['service_subtotal_rupiah' => 24000, 'discount_rupiah' => 0, 'express_surcharge_rupiah' => 0, 'delivery_fee_rupiah' => 0, 'total_rupiah' => 24000],
            ],
        ]);

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(AcceptOrder::class)->handle($quote, $normalized, $user, $membership->id)->invoice->original_total_rupiah),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(AcceptOrder::class)->handle($quote, $normalized, $user, $membership->id)->invoice->original_total_rupiah),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('code', 'QUOTE_CONSUMED')->count());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertNotNull($quote->refresh()->consumed_at);
        $this->assertDatabaseHas('order_lines', ['tenant_id' => $tenant->id, 'unit_price_rupiah' => 8000]);
        $this->assertDatabaseHas('invoices', ['tenant_id' => $tenant->id, 'original_total_rupiah' => 24000]);
    }

    public function test_two_repacks_cannot_move_more_than_the_remaining_source_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'sales_branch_id' => $branch->id, 'accepted_by_membership_id' => $membership->id]);
        $line = OrderLine::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $content = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bag->id, 'order_line_id' => $line->id, 'physical_quantity' => '3.000']);
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [], $user, 'Concurrency repack');

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RepackBag::class)->handle($order, [
                'source_bag_id' => $bag->public_id, 'reason' => 'Split', 'moves' => [['order_line_id' => $line->public_id, 'quantity' => '2.0']],
            ], $membership->id, $user)['source']->contents->sum('physical_quantity')),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RepackBag::class)->handle($order, [
                'source_bag_id' => $bag->public_id, 'reason' => 'Split', 'moves' => [['order_line_id' => $line->public_id, 'quantity' => '2.0']],
            ], $membership->id, $user)['source']->contents->sum('physical_quantity')),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertSame(3.0, (float) DB::table('bag_contents')->where('tenant_id', $tenant->id)->where('order_id', $order->id)->sum('physical_quantity'));
        $this->assertSame(1, DB::table('bag_repack_events')->where('tenant_id', $tenant->id)->count());
    }

    public function test_two_rewash_plans_reuse_one_idempotent_attempt(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'plant']);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'sales_branch_id' => $branch->id, 'accepted_by_membership_id' => $membership->id]);
        $line = OrderLine::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $content = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bag->id, 'order_line_id' => $line->id]);
        $complaint = new Complaint([
            'order_id' => $order->id, 'order_line_id' => $line->id, 'kind' => 'rewash',
            'status' => 'open', 'description' => 'Noda', 'opened_by_membership_id' => $membership->id,
        ]);
        $complaint->tenant_id = $tenant->id;
        $complaint->save();
        foreach ([['washing', 1, false], ['drying', 2, false], ['qc', 3, true]] as [$code, $sequence, $requiresQc]) {
            $task = new ProductionTask([
                'bag_content_id' => $content->id, 'production_location_id' => $location->id,
                'step_code' => $code, 'sequence' => $sequence, 'is_required' => true,
                'requires_qc' => $requiresQc, 'attempt_no' => 1, 'status' => 'done',
                'due_at' => now()->addDay(), 'completed_at' => now(),
            ]);
            $task->tenant_id = $tenant->id;
            $task->save();
        }
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [$location->id], $user, 'Concurrency rewash');

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => count(app(PlanRewash::class)->handle($complaint, $membership->id, $user))),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => count(app(PlanRewash::class)->handle($complaint, $membership->id, $user))),
        ]);

        $this->assertSame(2, collect($result)->where('ok', true)->count());
        $this->assertDatabaseCount('production_tasks', 6);
        $this->assertSame(3, DB::table('production_tasks')->where('tenant_id', $tenant->id)->where('attempt_no', 2)->count());
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'rewash_attempt_no' => 2]);
        DB::table('order_events')->delete();
    }

    public function test_command_receipt_replay_is_single_execution_across_processes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $command = (string) Str::uuid();
        $resultId = (string) Str::uuid();
        $payload = ['client_command_id' => $command, 'probe' => 'same'];

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(CommandReceiptService::class)->run(
                $tenant->id, $command, 'probe.command', $payload, $membership->id,
                fn (): bool => true,
                fn (): CommandResult => new CommandResult('tenant_membership', $resultId, ['executed' => true]),
                fn ($receipt): CommandResult => new CommandResult('tenant_membership', $receipt->result_public_id, ['executed' => true]),
            )->replay),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(CommandReceiptService::class)->run(
                $tenant->id, $command, 'probe.command', $payload, $membership->id,
                fn (): bool => true,
                fn (): CommandResult => new CommandResult('tenant_membership', $resultId, ['executed' => true]),
                fn ($receipt): CommandResult => new CommandResult('tenant_membership', $receipt->result_public_id, ['executed' => true]),
            )->replay),
        ]);

        $this->assertSame(2, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('result', false)->count());
        $this->assertSame(1, collect($result)->where('result', true)->count());
        $this->assertDatabaseCount('command_receipts', 1);
    }

    public function test_concurrent_shift_close_has_one_terminal_winner(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $register = CashRegister::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);
        $session = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $register->id,
            'cashier_membership_id' => $membership->id,
            'opening_rupiah' => 200000,
            'status' => 'open',
        ]);
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [], $user, 'Concurrency shift');

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(CloseCashSession::class)->handle($session, 200000, null, $membership->id)->status->value),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(CloseCashSession::class)->handle($session, 200000, null, $membership->id)->status->value),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertDatabaseHas('cash_sessions', ['id' => $session->id, 'status' => 'closed']);
        $this->assertDatabaseCount('cash_session_events', 1);
    }

    public function test_two_cash_payments_serialize_allocation_and_keep_change_ledger_consistent(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $register = CashRegister::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);
        $session = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $register->id,
            'cashier_membership_id' => $membership->id,
            'status' => 'open',
        ]);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'sales_branch_id' => $branch->id, 'accepted_by_membership_id' => $membership->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'sales_branch_id' => $branch->id, 'original_total_rupiah' => 100000]);
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [], $user, 'Concurrency cash');

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RecordPayment::class)->handle($invoice, [
                'amount_rupiah' => 80000, 'method' => 'cash',
                'cash_session_id' => $session->public_id, 'cash_received_rupiah' => 100000,
            ], $membership->id, $user)->amount_rupiah),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(RecordPayment::class)->handle($invoice, [
                'amount_rupiah' => 80000, 'method' => 'cash',
                'cash_session_id' => $session->public_id, 'cash_received_rupiah' => 100000,
            ], $membership->id, $user)->amount_rupiah),
        ]);

        $this->assertSame(2, collect($result)->where('ok', true)->count());
        $this->assertSame(100000, (int) DB::table('payment_allocations')->where('tenant_id', $tenant->id)->sum('amount_rupiah'));
        $this->assertSame(60000, (int) DB::table('payments')->where('tenant_id', $tenant->id)->sum('unallocated_rupiah'));
        $this->assertSame(160000, (int) DB::table('cash_entries')->where('tenant_id', $tenant->id)->sum('amount_rupiah'));
    }

    public function test_two_processes_cannot_reverse_more_than_the_net_allocation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'sales_branch_id' => $branch->id, 'accepted_by_membership_id' => $membership->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'sales_branch_id' => $branch->id, 'original_total_rupiah' => 100000]);
        $payment = new Payment([
            'received_branch_id' => $branch->id, 'method' => 'bank_manual', 'amount_rupiah' => 100000,
            'currency' => 'IDR', 'cash_received_rupiah' => 100000, 'change_rupiah' => 0,
            'unallocated_rupiah' => 0, 'received_at' => now(), 'received_by_membership_id' => $membership->id,
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();
        $allocation = new PaymentAllocation(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_rupiah' => 100000]);
        $allocation->tenant_id = $tenant->id;
        $allocation->save();
        app(UpdateMembershipAssignments::class)->handle($membership, [], [$branch->id], [], $user, 'Concurrency reversal');

        $result = $this->runConcurrently([
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(ReverseAllocation::class)->handle($allocation, 60000, 'Bersamaan', $membership->id, $user)->amount_rupiah),
            fn (): mixed => $this->withTenant($tenant, $user, fn () => app(ReverseAllocation::class)->handle($allocation, 60000, 'Bersamaan', $membership->id, $user)->amount_rupiah),
        ]);

        $this->assertSame(1, collect($result)->where('ok', true)->count());
        $this->assertSame(1, collect($result)->where('ok', false)->count());
        $this->assertSame(60000, (int) DB::table('allocation_reversals')->where('tenant_id', $tenant->id)->sum('amount_rupiah'));
    }

    public function test_failed_acceptance_rolls_back_order_invoice_and_quote_consumption(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard', 'is_active' => true]);
        $service = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Cuci Kiloan']);
        $input = ['branch_id' => $branch->public_id, 'fulfillment_method' => 'pickup', 'lines' => [['service_id' => $service->public_id, 'measured_quantity' => '2.3']]];
        $normalized = app(NormalizeOrderInput::class)->handle($input);
        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id, 'pickup_branch_id' => $branch->id,
            'request_hash' => app(NormalizeOrderInput::class)->requestHash($normalized),
            'expires_at' => now()->addMinutes(15), 'due_at' => now()->addDay(), 'total_rupiah' => 25000,
            'pricing_snapshot' => [
                'input' => ['service_discount_percent' => 0], 'customer_snapshot' => ['name' => 'Walk-in'],
                'lines' => [[
                    'service_id' => $service->public_id, 'unit' => 'kg', 'measured_quantity' => '2.300',
                    'billable_quantity' => '3.000', 'unit_price_rupiah' => 8000, 'line_total_rupiah' => 24000,
                    'base_total_rupiah' => 24000, 'discount_rupiah' => 0, 'express_surcharge_rupiah' => 0,
                    'pricing' => [], 'service_snapshot' => ['name' => 'Cuci Kiloan', 'workflow' => ['steps' => []]],
                    'due_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z'),
                ]],
                'totals' => ['service_subtotal_rupiah' => 24000, 'discount_rupiah' => 0, 'express_surcharge_rupiah' => 0, 'delivery_fee_rupiah' => 0, 'total_rupiah' => 25000],
            ],
        ]);

        try {
            $this->withTenant($tenant, $user, fn () => app(AcceptOrder::class)->handle($quote, $normalized, $user, $membership->id));
            $this->fail('Acceptance should have failed.');
        } catch (ApiException $exception) {
            $this->assertSame('QUOTE_TOTAL_MISMATCH', $exception->errorCode);
        }

        $this->assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('order_lines')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('invoices')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('bags')->where('tenant_id', $tenant->id)->count());
        $this->assertNull($quote->refresh()->consumed_at);
    }

    private function handover(Tenant $tenant, User $user, TenantMembership $membership, Order $order, Bag $bag, Location $location): array
    {
        return $this->withTenant($tenant, $user, fn () => app(CreateHandover::class)->handle($order, [
            'bag_ids' => [$bag->public_id],
            'location_id' => $location->public_id,
            'recipient_name' => 'Penerima',
            'verification_method' => 'receipt',
        ], $membership->id, $user));
    }

    /** @return array{Tenant, User, TenantMembership, Order, Bag, Location} */
    private function handoverScenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'outlet']);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'operational_status' => 'ready',
        ]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'sales_branch_id' => $branch->id, 'original_total_rupiah' => 100000]);
        $line = OrderLine::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'current_location_id' => $location->id]);
        $content = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bag->id, 'order_line_id' => $line->id]);
        $task = new ProductionTask([
            'bag_content_id' => $content->id, 'production_location_id' => $location->id,
            'step_code' => 'qc', 'sequence' => 1, 'is_required' => true, 'requires_qc' => true,
            'attempt_no' => 1, 'status' => 'done', 'due_at' => now()->addDay(), 'completed_at' => now(),
        ]);
        $task->tenant_id = $tenant->id;
        $task->save();
        $qc = new QcResult([
            'bag_content_id' => $content->id, 'production_task_id' => $task->id,
            'inspector_membership_id' => $membership->id, 'outcome' => 'passed',
            'checklist' => ['clean' => true], 'attempt_no' => 1,
        ]);
        $qc->tenant_id = $tenant->id;
        $qc->save();

        $payment = new Payment([
            'received_branch_id' => $branch->id, 'method' => 'bank_manual', 'amount_rupiah' => 100000,
            'currency' => 'IDR', 'cash_received_rupiah' => 100000, 'change_rupiah' => 0,
            'unallocated_rupiah' => 0, 'received_at' => now(), 'received_by_membership_id' => $membership->id,
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();
        $allocation = new PaymentAllocation(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_rupiah' => 100000]);
        $allocation->tenant_id = $tenant->id;
        $allocation->save();

        $role = Role::factory()->for($tenant)->create(['code' => 'handover', 'name' => 'Handover']);
        $permissions = collect([PermissionCode::HandoversManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => ['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [$location->id], $user, 'Concurrency handover');

        return [$tenant, $user, $membership, $order, $bag, $location];
    }

    private function withTenant(Tenant $tenant, User $user, \Closure $callback): mixed
    {
        return app(TenantScope::class)->forTenantId($tenant->id, $user->id, function () use ($tenant, $user, $callback): mixed {
            $context = app(MembershipAuthorizer::class)->contextFor($user, $tenant);
            if ($context === null) {
                throw new \RuntimeException('Tenant context is unavailable.');
            }
            app()->instance(TenantContext::class, $context);

            try {
                return $callback();
            } finally {
                app()->forgetInstance(TenantContext::class);
            }
        });
    }

    /**
     * @param  list<\Closure(): mixed>  $callbacks
     * @return list<array{ok: bool, result?: mixed, class?: string, code?: string}>
     */
    private function runConcurrently(array $callbacks): array
    {
        $dir = sys_get_temp_dir().'/netclean-concurrency-'.Str::uuid();
        mkdir($dir, 0700, true);
        $startAt = microtime(true) + 0.5;
        $pids = [];

        foreach ($callbacks as $index => $callback) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Unable to fork worker.');
            }
            if ($pid === 0) {
                DB::purge();
                try {
                    while (microtime(true) < $startAt) {
                        usleep(1000);
                    }
                    $result = $callback();
                    file_put_contents($dir.'/'.$index.'.json', json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR));
                } catch (\Throwable $exception) {
                    $code = $exception instanceof ApiException ? $exception->errorCode : $exception::class;
                    file_put_contents($dir.'/'.$index.'.json', json_encode(['ok' => false, 'class' => $exception::class, 'code' => $code], JSON_THROW_ON_ERROR));
                }
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach (array_keys($callbacks) as $index) {
            $path = $dir.'/'.$index.'.json';
            $this->assertFileExists($path);
            $results[] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            unlink($path);
        }
        rmdir($dir);

        return $results;
    }
}
