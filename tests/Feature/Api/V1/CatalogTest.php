<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Actions\CaptureServiceSnapshot;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_can_create_list_and_show_workflows_and_draft_services(): void
    {
        [$tenant, , $token] = $this->scenario();
        $workflow = $this->createWorkflow($tenant, $token);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', [
                'code' => 'kiloan',
                'name' => 'Cuci Kering Kiloan',
                'unit' => 'kg',
                'minimum_quantity' => '3.0',
                'quantity_step' => '0.1',
                'workflow_template_id' => $workflow->public_id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'kiloan')
            ->assertJsonPath('data.minimum_quantity', '3.000')
            ->assertJsonPath('data.quantity_step', '0.100')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sla_mode', 'working_days')
            ->assertJsonPath('data.regular_turnaround', 2)
            ->assertJsonPath('data.express_turnaround', 1)
            ->assertJsonPath('data.workflow_template.revision', 1)
            ->assertJsonCount(3, 'data.workflow_template.steps');

        $serviceId = $response->json('data.id');
        $this->assertDatabaseHas('services', [
            'tenant_id' => $tenant->id,
            'code' => 'kiloan',
            'is_active' => false,
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/services?active=0&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $serviceId)
            ->assertJsonPath('meta.per_page', 1);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/services/'.$serviceId)
            ->assertOk()
            ->assertJsonPath('data.workflow_template.code', 'standard')
            ->assertJsonPath('data.workflow_template.steps.2.code', 'qc');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/workflow-templates?active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workflow->public_id);
    }

    public function test_new_workflow_revision_preserves_old_snapshot_and_service_can_move_forward(): void
    {
        [$tenant, , $token] = $this->scenario();
        $firstWorkflow = $this->createWorkflow($tenant, $token);
        $service = $this->createService($tenant, $token, $firstWorkflow);
        $snapshotter = app(CaptureServiceSnapshot::class);
        $originalSnapshot = $snapshotter->handle($service);

        $secondResponse = $this->workflowRequest($tenant, $token, 'standard', [
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'drying', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'ironing', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'qc', 'is_required' => true, 'requires_qc' => true],
        ])->assertCreated()
            ->assertJsonPath('data.revision', 2)
            ->assertJsonPath('data.is_active', true);

        $secondWorkflow = WorkflowTemplate::query()
            ->where('public_id', $secondResponse->json('data.id'))
            ->sole();

        $this->assertFalse($firstWorkflow->refresh()->is_active);
        $this->assertSame(2, $firstWorkflow->version);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id, [
                'name' => 'Cuci Kering Premium',
                'workflow_template_id' => $secondWorkflow->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cuci Kering Premium')
            ->assertJsonPath('data.workflow_template.revision', 2)
            ->assertJsonCount(4, 'data.workflow_template.steps');

        $currentSnapshot = $snapshotter->handle($service->refresh());

        $this->assertSame('Cuci Kering Kiloan', $originalSnapshot['name']);
        $this->assertSame(1, $originalSnapshot['workflow']['revision']);
        $this->assertSame(['washing', 'drying', 'qc'], collect($originalSnapshot['workflow']['steps'])->pluck('code')->all());
        $this->assertSame('Cuci Kering Premium', $currentSnapshot['name']);
        $this->assertSame(2, $currentSnapshot['workflow']['revision']);
    }

    public function test_catalog_validation_rejects_invalid_workflows_quantities_foreign_templates_and_publish_attempts(): void
    {
        [$tenant, , $token] = $this->scenario();

        $this->workflowRequest($tenant, $token, 'invalid', [
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['steps.1.code']]]);

        $workflow = $this->createWorkflow($tenant, $token);
        $base = [
            'code' => 'satuan',
            'name' => 'Cuci Satuan',
            'unit' => 'pcs',
            'minimum_quantity' => '0.5',
            'quantity_step' => '1',
            'workflow_template_id' => $workflow->public_id,
        ];

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', $base)
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['minimum_quantity']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', [
                ...$base,
                'unit' => 'kg',
                'minimum_quantity' => '0',
                'quantity_step' => '0',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['quantity_step']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', [
                ...$base,
                'minimum_quantity' => '1',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.field_errors.is_active.0', 'This field is not allowed.');

        $otherWorkflow = WorkflowTemplate::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', [
                ...$base,
                'minimum_quantity' => '1',
                'workflow_template_id' => $otherWorkflow->public_id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.field_errors.workflow_template_id.0',
                'The selected workflow template must be an active revision in this tenant.',
            );
    }

    public function test_service_turnaround_mode_can_be_changed_within_mode_limits(): void
    {
        [$tenant, , $token] = $this->scenario();
        $workflow = $this->createWorkflow($tenant, $token);
        $service = $this->createService($tenant, $token, $workflow);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id, [
                'sla_mode' => 'elapsed_hours',
                'regular_turnaround' => 24,
                'express_turnaround' => 6,
            ])
            ->assertOk()
            ->assertJsonPath('data.sla_mode', 'elapsed_hours')
            ->assertJsonPath('data.regular_turnaround', 24)
            ->assertJsonPath('data.express_turnaround', 6);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id, [
                'regular_turnaround' => 6,
                'express_turnaround' => 24,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['express_turnaround']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id, [
                'sla_mode' => 'working_days',
                'regular_turnaround' => 400,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['regular_turnaround']]]);
    }

    public function test_catalog_endpoints_enforce_authentication_permissions_and_tenant_isolation(): void
    {
        [$tenant, $user, $token, $role] = $this->scenario();

        $this->getJson('/api/v1/services')->assertUnauthorized();

        $workflow = $this->createWorkflow($tenant, $token);
        $service = $this->createService($tenant, $token, $workflow);
        $otherService = Service::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/services/'.$otherService->public_id)
            ->assertNotFound();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::CatalogView->value)->sole()->id],
            $user,
            'Downgrade to catalog viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->workflowRequest($tenant, $token, 'denied', [
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id, ['name' => 'Denied'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_workflow_definition_and_steps_cannot_be_mutated_in_place(): void
    {
        [$tenant, , $token] = $this->scenario();
        $workflow = $this->createWorkflow($tenant, $token);
        $step = $workflow->steps()->firstOrFail();

        try {
            DB::transaction(function () use ($workflow): void {
                $workflow->forceFill(['name' => 'Mutated workflow'])->save();
            });
            $this->fail('Workflow definition was mutated without creating a revision.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }

        try {
            DB::transaction(function () use ($step): void {
                $step->forceFill(['code' => 'mutated-step'])->save();
            });
            $this->fail('Workflow step was mutated without creating a revision.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
    }

    private function createWorkflow(Tenant $tenant, string $token, string $code = 'standard'): WorkflowTemplate
    {
        $response = $this->workflowRequest($tenant, $token, $code, [
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'drying', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'qc', 'is_required' => true, 'requires_qc' => true],
        ])->assertCreated();

        return WorkflowTemplate::query()->where('public_id', $response->json('data.id'))->sole();
    }

    private function createService(Tenant $tenant, string $token, WorkflowTemplate $workflow): Service
    {
        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/services', [
                'code' => 'kiloan',
                'name' => 'Cuci Kering Kiloan',
                'unit' => 'kg',
                'minimum_quantity' => '3.0',
                'quantity_step' => '0.1',
                'workflow_template_id' => $workflow->public_id,
            ])
            ->assertCreated();

        return Service::query()->where('public_id', $response->json('data.id'))->sole();
    }

    /**
     * @param  list<array{code: string, is_required: bool, requires_qc: bool}>  $steps
     */
    private function workflowRequest(Tenant $tenant, string $token, string $code, array $steps): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/workflow-templates', [
                'code' => $code,
                'name' => ucfirst($code).' workflow',
                'steps' => $steps,
            ]);
    }

    /** @return array{Tenant, User, string, Role} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'catalog-admin', 'name' => 'Catalog admin']);

        $permissionIds = collect([PermissionCode::CatalogView, PermissionCode::CatalogManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [], [], $user, 'Bootstrap catalog admin');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Catalog test device');

        return [$tenant, $user, $issued['token']->plainTextToken, $role];
    }
}
