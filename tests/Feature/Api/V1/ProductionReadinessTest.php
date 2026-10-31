<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_ready_and_ready_require_all_parts_and_latest_qc_pass(): void
    {
        [$tenant, $token, $order, $location, $contentA, $contentB] = $this->scenario();

        $planned = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/production-tasks', ['location_id' => $location->public_id])
            ->assertCreated()
            ->assertJsonCount(6, 'data');

        $tasksA = ProductionTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('bag_content_id', $contentA->id)
            ->orderBy('sequence')
            ->get();
        $tasksB = ProductionTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('bag_content_id', $contentB->id)
            ->orderBy('sequence')
            ->get();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$tasksA[2]->public_id.'/qc-results', [
                'outcome' => 'passed',
                'checklist' => ['clean' => true],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QC_TASK_NOT_READY');

        $this->complete($tenant, $token, $tasksA);
        $this->complete($tenant, $token, $tasksB->take(2));

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.can_ready', false)
            ->assertJsonPath('data.partially_ready', false);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$tasksA[2]->public_id.'/qc-results', [
                'outcome' => 'failed',
                'checklist' => ['clean' => false],
                'notes' => 'Noda masih terlihat',
            ])
            ->assertOk()
            ->assertJsonPath('data.readiness.can_ready', false);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$tasksA[2]->public_id.'/qc-results', [
                'outcome' => 'passed',
                'checklist' => ['clean' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.readiness.partially_ready', true);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.partially_ready', true)
            ->assertJsonPath('data.operational_status', 'partially_ready');

        $this->complete($tenant, $token, $tasksB, startAt: 2);
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$tasksB[2]->public_id.'/qc-results', [
                'outcome' => 'passed',
                'checklist' => ['clean' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.readiness.can_ready', true);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'ready')
            ->assertJsonPath('data.can_ready', true);
    }

    private function complete(Tenant $tenant, string $token, Collection $tasks, int $startAt = 0): void
    {
        foreach ($tasks->slice($startAt)->values() as $task) {
            $this->withToken($token)
                ->withHeader('X-Tenant-ID', $tenant->public_id)
                ->postJson('/api/v1/production/tasks/'.$task->public_id.'/transitions', [
                    'expected_version' => $task->version,
                    'to_status' => 'in_progress',
                ])
                ->assertOk();
            $this->withToken($token)
                ->withHeader('X-Tenant-ID', $tenant->public_id)
                ->postJson('/api/v1/production/tasks/'.$task->public_id.'/transitions', [
                    'expected_version' => $task->version + 1,
                    'to_status' => 'done',
                ])
                ->assertOk();
        }
    }

    /** @return array{Tenant, string, Order, Location, BagContent, BagContent} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'qc', 'name' => 'QC']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'plant']);
        $permissions = collect([PermissionCode::ProductionView, PermissionCode::ProductionManage, PermissionCode::ProductionQc])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [$location->id], $user, 'Bootstrap QC');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard', 'is_active' => true]);
        $service = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Cuci Kiloan']);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'operational_status' => 'accepted',
        ]);
        $line = OrderLine::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'service_id' => $service->id,
            'service_snapshot' => [
                'name' => 'Cuci Kiloan',
                'express_surcharge_rupiah' => 0,
                'workflow' => ['steps' => [
                    ['code' => 'washing', 'sequence' => 1, 'is_required' => true, 'requires_qc' => false],
                    ['code' => 'drying', 'sequence' => 2, 'is_required' => true, 'requires_qc' => false],
                    ['code' => 'qc', 'sequence' => 3, 'is_required' => true, 'requires_qc' => true],
                ]],
            ],
        ]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $contentA = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bag->id, 'order_line_id' => $line->id]);
        $bagB = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $contentB = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bagB->id, 'order_line_id' => $line->id]);

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'QC device')['token']->plainTextToken;

        return [$tenant, $token, $order, $location, $contentA, $contentB];
    }
}
