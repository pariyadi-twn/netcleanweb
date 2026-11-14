<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class WebTenantContextTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    public function test_dashboard_redirects_to_tenant_selection_without_a_session_context(): void
    {
        [$owner] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect(route('tenants.select'));
    }

    public function test_tenant_selection_only_lists_the_authenticated_users_tenants(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();
        [, $otherTenant] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->get('/tenants/select')
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertDontSee($otherTenant->name);
    }

    public function test_selecting_an_authorized_tenant_opens_the_scoped_dashboard(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->post('/tenants/select', ['tenant_id' => $tenant->public_id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('tenant.public_id', $tenant->public_id);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee('Konteks tenant terlindungi');
    }

    public function test_selecting_another_users_tenant_returns_not_found(): void
    {
        [$owner] = $this->confirmedOwner();
        [, $otherTenant] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->post('/tenants/select', ['tenant_id' => $otherTenant->public_id])
            ->assertNotFound()
            ->assertSessionMissing('tenant.public_id');
    }

    public function test_invalid_tenant_selection_is_rejected_without_changing_context(): void
    {
        [$owner] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->from('/tenants/select')
            ->post('/tenants/select', ['tenant_id' => 'not-a-uuid'])
            ->assertRedirect('/tenants/select')
            ->assertSessionHasErrors('tenant_id')
            ->assertSessionMissing('tenant.public_id');
    }

    public function test_forged_session_context_returns_not_found_and_is_removed(): void
    {
        [$owner] = $this->confirmedOwner();

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => (string) Str::uuid()])
            ->get('/dashboard')
            ->assertNotFound()
            ->assertSessionMissing('tenant.public_id');
    }

    public function test_tenant_names_are_escaped_in_the_selection_page(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();
        $tenant->forceFill(['name' => '<script>alert("tenant")</script>'])->save();

        $this->actingAs($owner)
            ->get('/tenants/select')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("tenant")</script>', false);
    }

    public function test_disabled_membership_returns_forbidden_and_clears_the_session(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();
        TenantMembership::query()
            ->where('user_id', $owner->id)
            ->where('tenant_id', $tenant->id)
            ->update(['status' => 'disabled']);

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSessionMissing('tenant.public_id');
    }

    public function test_suspended_tenant_returns_forbidden_and_clears_the_session(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();
        Tenant::query()->whereKey($tenant->id)->update(['status' => 'suspended']);

        $this->actingAs($owner)
            ->get('/tenants/select')
            ->assertOk()
            ->assertDontSee($tenant->name);

        $this->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSessionMissing('tenant.public_id');
    }

    public function test_switching_tenants_uses_only_the_new_scope(): void
    {
        [$owner, $firstTenant] = $this->confirmedOwner();
        $secondTenant = Tenant::factory()->create();
        $secondMembership = TenantMembership::factory()->for($secondTenant)->for($owner)->create(['status' => 'active']);
        $secondRole = Role::factory()->for($secondTenant)->create(['code' => RoleCode::Owner->value, 'name' => 'Owner']);
        $this->grantConsolePermission($secondTenant, $secondRole);
        $secondMembership->roles()->attach($secondRole->id, ['tenant_id' => $secondTenant->id]);

        $this->actingAs($owner)
            ->post('/tenants/select', ['tenant_id' => $firstTenant->public_id])
            ->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee($firstTenant->name);

        $this->post('/tenants/select', ['tenant_id' => $secondTenant->public_id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('tenant.public_id', $secondTenant->public_id);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($secondTenant->name)
            ->assertDontSee($firstTenant->name);
    }

    public function test_member_without_console_permission_cannot_open_the_dashboard(): void
    {
        [$owner, $tenant] = $this->confirmedOwner();
        $cashier = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($cashier)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Kasir']);
        $membership->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        $this->actingAs($cashier)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/dashboard')
            ->assertForbidden();
    }

    /** @return array{User, Tenant} */
    private function confirmedOwner(): array
    {
        $owner = $this->createOwner();
        $this->enableMfa($owner);
        $tenantId = TenantMembership::query()
            ->where('user_id', $owner->id)
            ->latest('id')
            ->value('tenant_id');
        $tenant = Tenant::query()->findOrFail($tenantId);
        $role = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', RoleCode::Owner->value)
            ->sole();
        $this->grantConsolePermission($tenant, $role);

        return [$owner, $tenant];
    }

    private function grantConsolePermission(Tenant $tenant, Role $role): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => PermissionCode::ReportsView->value],
            ['description' => 'Lihat laporan'],
        );
        DB::table('role_permissions')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }
}
