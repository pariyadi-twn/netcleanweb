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
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplaintRewashTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rewash_clones_attempt_without_revenue_and_resets_readiness_until_new_qc(): void
    {
        [$tenant, $token, $order, $line, $content, $location, $originalTasks] = $this->scenario();

        $complaint = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/complaints', [
                'kind' => 'rewash',
                'order_line_id' => $line->public_id,
                'description' => 'Noda membandel pada bagian kerah',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.kind', 'rewash');

        $complaintId = $complaint->json('data.complaint_id');
        $invoiceCount = DB::table('invoices')->where('tenant_id', $tenant->id)->count();

        $rewash = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/complaints/'.$complaintId.'/rewash')
            ->assertCreated()
            ->assertJsonCount(3, 'data');

        $newTasks = ProductionTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('bag_content_id', $content->id)
            ->where('attempt_no', 2)
            ->orderBy('sequence')
            ->get();
        $this->assertCount(3, $newTasks);
        $this->assertSame($originalTasks[0]->id, $newTasks[0]->original_task_id);
        $this->assertSame('queued', $newTasks[0]->status->value);
        $this->assertSame($invoiceCount, DB::table('invoices')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('complaints', ['public_id' => $complaintId, 'status' => 'investigating', 'rewash_attempt_no' => 2]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.can_ready', false);

        $this->complete($tenant, $token, $newTasks);
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/production/tasks/'.$newTasks[2]->public_id.'/qc-results', [
                'outcome' => 'passed',
                'checklist' => ['clean' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.readiness.can_ready', true);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/complaints/'.$complaintId.'/resolution', [
                'status' => 'resolved',
                'resolution' => 'Rewash selesai dan lolos QC',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_complaint_requires_permission_and_order_line_must_belong_to_order(): void
    {
        [$tenant, $token, $order, $line] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/complaints', [
                'kind' => 'damaged',
                'description' => 'Rusak',
            ])
            ->assertCreated();

        $other = OrderLine::factory()->create();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/complaints', [
                'kind' => 'damaged',
                'order_line_id' => $other->public_id,
                'description' => 'Baris asing',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['order_line_id']]]);
    }

    private function complete(Tenant $tenant, string $token, Collection $tasks): void
    {
        foreach ($tasks as $task) {
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

    /** @return array{Tenant, string, Order, OrderLine, BagContent, Location, Collection} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'qc', 'name' => 'QC']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'plant']);
        $permissions = collect([PermissionCode::ProductionView, PermissionCode::ProductionManage, PermissionCode::ProductionQc, PermissionCode::ComplaintsManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [$location->id], $user, 'Bootstrap complaint operator');

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
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'sales_branch_id' => $branch->id]);
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
        $content = BagContent::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'bag_id' => $bag->id, 'order_line_id' => $line->id]);

        $originalTasks = collect();
        foreach ([['washing', 1, false], ['drying', 2, false], ['qc', 3, true]] as [$code, $sequence, $requiresQc]) {
            $task = new ProductionTask([
                'bag_content_id' => $content->id,
                'production_location_id' => $location->id,
                'step_code' => $code,
                'sequence' => $sequence,
                'is_required' => true,
                'requires_qc' => $requiresQc,
                'attempt_no' => 1,
                'status' => 'done',
                'due_at' => now()->addDay(),
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(10),
            ]);
            $task->tenant_id = $tenant->id;
            $task->save();
            $originalTasks->push($task);
        }

        $qc = new QcResult([
            'bag_content_id' => $content->id,
            'production_task_id' => $originalTasks[2]->id,
            'inspector_membership_id' => $membership->id,
            'outcome' => 'passed',
            'checklist' => ['clean' => true],
            'attempt_no' => 1,
        ]);
        $qc->tenant_id = $tenant->id;
        $qc->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Complaint device')['token']->plainTextToken;

        return [$tenant, $token, $order, $line, $content, $location, $originalTasks];
    }
}
