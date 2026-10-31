<?php

namespace Tests\Feature\Order;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Order\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_order_policy_enforces_view_manage_permissions_and_branch_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $branch = Branch::factory()->for($tenant)->create();
        $otherBranch = Branch::factory()->for($tenant)->create();
        $otherTenantBranch = Branch::factory()->create();
        $order = (new Order(['sales_branch_id' => $branch->id]))->forceFill(['tenant_id' => $tenant->id]);
        $otherOrder = (new Order(['sales_branch_id' => $otherBranch->id]))->forceFill(['tenant_id' => $tenant->id]);
        $crossTenantOrder = (new Order(['sales_branch_id' => $otherTenantBranch->id]))->forceFill(['tenant_id' => $tenant->id]);

        $viewer = new OrderPolicy($this->context($tenant, $user, [$branch->id], [PermissionCode::OrdersView]));
        $this->assertTrue($viewer->viewAny($user));
        $this->assertTrue($viewer->view($user, $order));
        $this->assertFalse($viewer->view($user, $otherOrder));
        $this->assertFalse($viewer->view($user, $crossTenantOrder));
        $this->assertFalse($viewer->create($user));

        $manager = new OrderPolicy($this->context($tenant, $user, [$branch->id], [PermissionCode::OrdersManage]));
        $this->assertFalse($manager->viewAny($user));
        $this->assertFalse($manager->view($user, $order));
        $this->assertTrue($manager->create($user));
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<PermissionCode>  $permissions
     */
    private function context(Tenant $tenant, User $user, array $branchIds, array $permissions): TenantContext
    {
        return new TenantContext(
            userId: $user->id,
            tenantId: $tenant->id,
            tenantPublicId: $tenant->public_id,
            membershipId: 1,
            membershipPublicId: (string) fake()->uuid(),
            permissionVersion: 1,
            branchIds: $branchIds,
            locationIds: [],
            permissions: array_map(fn (PermissionCode $permission): string => $permission->value, $permissions),
        );
    }
}
