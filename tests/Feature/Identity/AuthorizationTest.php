<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_role_can_manage_only_assigned_branch_and_location(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = $this->membership($tenant, $user);
        $branch = Branch::factory()->for($tenant)->create();
        $otherBranch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();
        $otherLocation = Location::factory()->atBranch($otherBranch)->create();
        $otherTenantBranch = Branch::factory()->create();
        $ownerRole = $this->role($tenant, 'owner', PermissionCode::cases());

        app(UpdateMembershipAssignments::class)->handle(
            $membership,
            [$ownerRole->id],
            [$branch->id],
            [$location->id],
        );

        $context = app(MembershipAuthorizer::class)->contextFor($user, $tenant);

        $this->assertNotNull($context);
        $this->assertTrue($context->can(PermissionCode::BranchesManage));
        $this->assertTrue($context->can(PermissionCode::MembershipsManage));
        $this->assertSame(2, $context->permissionVersion);

        $this->app->instance(TenantContext::class, $context);

        $this->assertTrue(Gate::forUser($user)->allows('view', $branch));
        $this->assertTrue(Gate::forUser($user)->allows('manage', $branch));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherBranch));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $otherBranch));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $otherTenantBranch));

        $this->assertTrue(Gate::forUser($user)->allows('view', $location));
        $this->assertTrue(Gate::forUser($user)->allows('manage', $location));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherLocation));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $otherLocation));

        $this->assertTrue(Gate::forUser($user)->allows('view', $membership));
        $this->assertTrue(Gate::forUser($user)->allows('manage', $membership));
        $this->assertTrue(Gate::forUser($user)->allows('manage', $ownerRole));
    }

    public function test_viewer_role_cannot_manage_and_cannot_escape_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = $this->membership($tenant, $user);
        $branch = Branch::factory()->for($tenant)->create();
        $otherBranch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();
        $otherLocation = Location::factory()->atBranch($otherBranch)->create();
        $viewerRole = $this->role($tenant, 'viewer', [PermissionCode::BranchesView, PermissionCode::LocationsView]);

        app(UpdateMembershipAssignments::class)->handle(
            $membership,
            [$viewerRole->id],
            [$branch->id],
            [$location->id],
        );

        $context = app(MembershipAuthorizer::class)->contextFor($user, $tenant);
        $this->assertNotNull($context);
        $this->app->instance(TenantContext::class, $context);

        $this->assertTrue(Gate::forUser($user)->allows('view', $branch));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $branch));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherBranch));
        $this->assertTrue(Gate::forUser($user)->allows('view', $location));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $location));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherLocation));
        $this->assertFalse(Gate::forUser($user)->allows('view', $membership));
        $this->assertFalse(Gate::forUser($user)->allows('manage', $viewerRole));
    }

    public function test_permission_version_invalidates_cached_authorization_after_role_change(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = $this->membership($tenant, $user);
        $branch = Branch::factory()->for($tenant)->create();
        $role = $this->role($tenant, 'staff', [PermissionCode::BranchesView]);
        $authorizer = app(MembershipAuthorizer::class);

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], []);

        $before = $authorizer->contextFor($user, $tenant);
        $this->assertNotNull($before);
        $this->assertFalse($before->can(PermissionCode::BranchesManage));

        $beforeVersion = $membership->refresh()->permission_version;

        app(SyncRolePermissions::class)->handle($role, [
            $this->permission(PermissionCode::BranchesView)->id,
            $this->permission(PermissionCode::BranchesManage)->id,
        ]);

        $after = $authorizer->contextFor($user, $tenant);
        $this->assertNotNull($after);
        $this->assertTrue($after->can(PermissionCode::BranchesManage));
        $this->assertGreaterThan($beforeVersion, $after->permissionVersion);
    }

    public function test_assignment_changes_bump_permission_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = $this->membership($tenant, $user);
        $role = $this->role($tenant, 'staff', [PermissionCode::BranchesView]);
        $firstBranch = Branch::factory()->for($tenant)->create();
        $secondBranch = Branch::factory()->for($tenant)->create();

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$firstBranch->id], []);
        $version = $membership->refresh()->permission_version;

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$secondBranch->id], []);

        $this->assertSame($version + 1, $membership->refresh()->permission_version);
        $context = app(MembershipAuthorizer::class)->contextFor($user, $tenant);
        $this->assertNotNull($context);
        $this->assertSame([$secondBranch->id], $context->branchIds);
    }

    public function test_inactive_membership_or_tenant_has_no_context(): void
    {
        $user = User::factory()->create();
        $disabledMembership = TenantMembership::factory()->for($user)->create(['status' => 'disabled']);
        $suspendedTenant = Tenant::factory()->create(['status' => 'suspended']);
        TenantMembership::factory()->for($suspendedTenant)->for($user)->create(['status' => 'active']);
        $authorizer = app(MembershipAuthorizer::class);

        $this->assertNull($authorizer->contextFor($user, $disabledMembership->tenant));
        $this->assertNull($authorizer->contextFor($user, $suspendedTenant));
    }

    public function test_assignments_cannot_reference_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->create(['status' => 'active']);
        $otherRole = Role::factory()->for($otherTenant)->create();
        $otherBranch = Branch::factory()->for($otherTenant)->create();
        $otherLocation = Location::factory()->atBranch($otherBranch)->create();
        $action = app(UpdateMembershipAssignments::class);

        $attempts = [
            'roles' => fn () => $action->handle($membership, [$otherRole->id], [], []),
            'branches' => fn () => $action->handle($membership, [], [$otherBranch->id], []),
            'locations' => fn () => $action->handle($membership, [], [], [$otherLocation->id]),
        ];

        foreach ($attempts as $field => $attempt) {
            try {
                $attempt();
                $this->fail('Cross-tenant assignment was accepted for '.$field.'.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertSame(0, DB::table('membership_roles')->count());
        $this->assertSame(0, DB::table('branch_assignments')->count());
        $this->assertSame(0, DB::table('location_assignments')->count());
    }

    public function test_role_permission_sync_rejects_unknown_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $role = Role::factory()->for($tenant)->create();

        $this->expectException(ValidationException::class);

        app(SyncRolePermissions::class)->handle($role, [PHP_INT_MAX]);
    }

    private function membership(Tenant $tenant, User $user): TenantMembership
    {
        return TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
    }

    private function permission(PermissionCode $code): Permission
    {
        return Permission::query()->firstOrCreate(
            ['code' => $code->value],
            ['description' => $code->name],
        );
    }

    /**
     * @param  list<PermissionCode>  $permissions
     */
    private function role(Tenant $tenant, string $code, array $permissions): Role
    {
        $role = Role::factory()->for($tenant)->create([
            'code' => $code,
            'name' => ucfirst($code),
        ]);

        if ($permissions !== []) {
            DB::table('role_permissions')->insert(array_map(
                fn (PermissionCode $permission): array => [
                    'tenant_id' => $tenant->id,
                    'role_id' => $role->id,
                    'permission_id' => $this->permission($permission)->id,
                ],
                $permissions,
            ));
        }

        return $role;
    }
}
