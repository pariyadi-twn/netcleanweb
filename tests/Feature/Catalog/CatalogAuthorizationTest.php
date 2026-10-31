<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Tenant;
use App\Policies\ServicePolicy;
use App\Policies\WorkflowTemplatePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_permissions_and_tenant_ownership_define_the_policy_matrix(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create();
        $otherWorkflow = WorkflowTemplate::factory()->create();
        $otherService = Service::factory()->forWorkflow($otherWorkflow)->create();

        $viewerContext = $this->context($tenant, $user, [PermissionCode::CatalogView]);
        $viewerServices = new ServicePolicy($viewerContext);
        $viewerWorkflows = new WorkflowTemplatePolicy($viewerContext);

        $this->assertTrue($viewerServices->viewAny($user));
        $this->assertTrue($viewerServices->view($user, $service));
        $this->assertFalse($viewerServices->create($user));
        $this->assertFalse($viewerServices->update($user, $service));
        $this->assertFalse($viewerServices->view($user, $otherService));
        $this->assertTrue($viewerWorkflows->viewAny($user));
        $this->assertTrue($viewerWorkflows->view($user, $workflow));
        $this->assertFalse($viewerWorkflows->create($user));
        $this->assertFalse($viewerWorkflows->view($user, $otherWorkflow));

        $managerContext = $this->context($tenant, $user, [PermissionCode::CatalogManage]);
        $managerServices = new ServicePolicy($managerContext);
        $managerWorkflows = new WorkflowTemplatePolicy($managerContext);

        $this->assertFalse($managerServices->viewAny($user));
        $this->assertTrue($managerServices->create($user));
        $this->assertTrue($managerServices->update($user, $service));
        $this->assertFalse($managerServices->update($user, $otherService));
        $this->assertFalse($managerWorkflows->viewAny($user));
        $this->assertTrue($managerWorkflows->create($user));
    }

    /**
     * @param  list<PermissionCode>  $permissions
     */
    private function context(Tenant $tenant, User $user, array $permissions): TenantContext
    {
        return new TenantContext(
            userId: $user->id,
            tenantId: $tenant->id,
            tenantPublicId: $tenant->public_id,
            membershipId: 1,
            membershipPublicId: (string) fake()->uuid(),
            permissionVersion: 1,
            branchIds: [],
            locationIds: [],
            permissions: array_map(fn (PermissionCode $permission): string => $permission->value, $permissions),
        );
    }
}
