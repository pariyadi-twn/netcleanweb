<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
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
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Actions\RequestRefund;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use App\Modules\Saas\Actions\ConsumeQuota;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Models\SaasPlan;
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
