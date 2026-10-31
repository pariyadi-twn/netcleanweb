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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionTaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_plan_queue_prerequisite_and_transitions_are_operator_and_location_scoped(): void
    {
        [$tenant, $token, $order, $location, $otherLocation] = $this->scenario();

        $planned = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/production-tasks', [
                'location_id' => $location->public_id,
            ])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.step_code', 'washing')
            ->assertJsonPath('data.0.status', 'queued')
            ->assertJsonPath('data.0.sequence', 1)
            ->assertJsonPath('data.0.service_name', 'Cuci Kiloan');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/production-tasks', [
                'location_id' => $location->public_id,
            ])
            ->assertCreated()
            ->assertJsonCount(3, 'data');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/production-tasks', [
                'location_id' => $otherLocation->public_id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCTION_ALREADY_PLANNED');

        $queue = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/production/tasks?location_id='.$location->public_id.'&compact=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertSame(['washing', 'drying', 'qc'], collect($queue->json('data'))->pluck('step_code')->all());
        $this->assertArrayNotHasKey('location_id', $queue->json('data.0'));

        $washing = $planned->json('data.0.task_id');
        $drying = $planned->json('data.1.task_id');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$drying.'/transitions', [
                'expected_version' => 1,
                'to_status' => 'in_progress',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCTION_PREREQUISITE_OPEN');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$washing.'/transitions', [
                'expected_version' => 1,
                'to_status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.version', 2);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$washing.'/transitions', [
                'expected_version' => 2,
                'to_status' => 'blocked',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['reason']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$washing.'/transitions', [
                'expected_version' => 2,
                'to_status' => 'done',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$washing.'/transitions', [
                'expected_version' => 1,
                'to_status' => 'in_progress',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCTION_TASK_VERSION_CONFLICT');

        $this->assertDatabaseHas('production_task_events', ['tenant_id' => $tenant->id, 'to_status' => 'done']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'production.task_transitioned']);
    }

    public function test_operator_without_location_assignment_cannot_see_queue(): void
    {
        [$tenant, $token, , $location] = $this->scenario(assignLocation: false);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/production/tasks?location_id='.$location->public_id)
            ->assertForbidden();
    }

    /** @return array{Tenant, string, Order, Location, Location} */
    private function scenario(bool $assignLocation = true): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'operator', 'name' => 'Operator']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'plant']);
        $otherLocation = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'plant']);
        $permissions = collect([PermissionCode::ProductionView, PermissionCode::ProductionManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle(
            $membership,
            [$role->id],
            [$branch->id],
            $assignLocation ? [$location->id, $otherLocation->id] : [],
            $user,
            'Bootstrap operator',
        );

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
                'workflow' => [
                    'steps' => [
                        ['code' => 'washing', 'sequence' => 1, 'is_required' => true, 'requires_qc' => false],
                        ['code' => 'drying', 'sequence' => 2, 'is_required' => true, 'requires_qc' => false],
                        ['code' => 'qc', 'sequence' => 3, 'is_required' => true, 'requires_qc' => true],
                    ],
                ],
            ],
        ]);
        $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);
        $content = BagContent::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'bag_id' => $bag->id,
            'order_line_id' => $line->id,
        ]);

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Operator device')['token']->plainTextToken;

        return [$tenant, $token, $order, $location, $otherLocation];
    }
}
