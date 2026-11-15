<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Database\Seeders\IdentityPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class OwnerWebPagesTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_owner_can_update_organization_profile(): void
    {
        [$owner, $tenant] = $this->ownerContext();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/organization')
            ->assertOk()
            ->assertSee('Keterangan')
            ->assertDontSee('name="timezone"', false);

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->put('/organization', [
                'name' => 'Organisasi Diperbarui',
                'reason' => 'Uji profil',
            ])->assertRedirect(route('organization.index'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Organisasi Diperbarui',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_owner_can_create_and_configure_a_role(): void
    {
        [$owner, $tenant] = $this->ownerContext();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->post('/roles', [
                'code' => 'web-supervisor',
                'name' => 'Web Supervisor',
                'reason' => 'Uji peran',
            ])->assertRedirect(route('roles.index'));

        $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'web-supervisor')->sole();
        $permission = Permission::query()->where('code', 'orders.view')->sole();

        $this->put('/roles/'.$role->public_id, [
            'name' => 'Web Supervisor Diperbarui',
            'permission_ids' => [$permission->id],
            'reason' => 'Uji izin',
        ])->assertRedirect(route('roles.edit', $role));

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Web Supervisor Diperbarui']);
        $this->assertDatabaseHas('role_permissions', [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_owner_can_view_and_update_a_branch(): void
    {
        [$owner, $tenant, $branch] = $this->ownerContext();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/branches')
            ->assertOk()
            ->assertSee($branch->name);

        $this->put('/branches/'.$branch->public_id, [
            'name' => 'Cabang Diperbarui',
            'address' => 'Jl. Baru No. 1',
            'phone' => '081234567890',
            'is_active' => '1',
            'reason' => 'Uji web',
        ])->assertRedirect(route('branches.edit', $branch));

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'public_id' => $branch->public_id,
            'name' => 'Cabang Diperbarui',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_owner_can_manage_staff_assignments(): void
    {
        [$owner, $tenant, $branch, $location, $membership, $role] = $this->ownerContext();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/staff')
            ->assertOk()
            ->assertSee($owner->name);

        $this->put('/staff/'.$membership->public_id, [
            'role_ids' => [$role->id],
            'branch_ids' => [$branch->id],
            'location_ids' => [$location->id],
            'reason' => 'Uji penugasan',
        ])->assertRedirect(route('staff.edit', $membership));

        $this->assertDatabaseHas('branch_assignments', [
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_owner_can_create_service_price_and_publish_it(): void
    {
        [$owner, $tenant] = $this->ownerContext();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['is_active' => true]);

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id]);

        $this->post('/catalog/services', [
            'code' => 'web-kiloan',
            'name' => 'Kiloan Web',
            'unit' => 'kg',
            'minimum_quantity' => '3.000',
            'quantity_step' => '0.100',
            'workflow_template_id' => $workflow->public_id,
        ])->assertRedirect(route('catalog.index'));

        $service = Service::query()->where('code', 'web-kiloan')->sole();

        $this->post('/catalog/price-books', [
            'name' => 'Harga Web',
            'branch_id' => null,
        ])->assertRedirect(route('catalog.index'));

        $book = PriceBook::query()->where('name', 'Harga Web')->sole();

        $this->post('/catalog/price-books/'.$book->public_id.'/entries', [
            'service_id' => $service->public_id,
            'tier' => 'regular',
            'unit_price_rupiah' => 9000,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect(route('catalog.index'));

        $this->post('/catalog/services/'.$service->public_id.'/publication', [
            'is_active' => '1',
        ])->assertRedirect(route('catalog.index'));

        $this->assertTrue($service->refresh()->is_active);
        $this->assertDatabaseHas('price_entries', [
            'tenant_id' => $tenant->id,
            'price_book_id' => $book->id,
            'service_id' => $service->id,
            'unit_price_rupiah' => 9000,
        ]);
        $this->assertSame(1, PriceEntry::query()->count());
    }

    public function test_owner_can_search_and_create_customers(): void
    {
        [$owner, $tenant] = $this->ownerContext();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->post('/customers', [
                'name' => 'Pelanggan Web',
                'phone' => '081234567891',
                'email' => 'web@example.test',
            ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Web',
        ]);

        $this->get('/customers?q=Pelanggan')
            ->assertOk()
            ->assertSee('Pelanggan Web');
    }

    public function test_cross_tenant_branch_is_not_found(): void
    {
        [$owner, $tenant] = $this->ownerContext();
        $other = Tenant::factory()->create();
        $otherBranch = Branch::factory()->for($other)->create();

        $this->actingAs($owner)->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/branches/'.$otherBranch->public_id)
            ->assertNotFound();
    }

    /** @return array{User, Tenant, Branch, Location, TenantMembership, Role} */
    private function ownerContext(): array
    {
        $this->seed(IdentityPermissionSeeder::class);
        $owner = $this->createOwner();
        $this->enableMfa($owner);
        $tenant = Tenant::query()->whereHas('memberships', fn ($query) => $query->where('user_id', $owner->id))->sole();
        $membership = TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $owner->id)->sole();
        $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'owner')->sole();
        $branch = Branch::factory()->for($tenant)->create(['name' => 'Cabang Uji']);
        $location = Location::factory()->atBranch($branch)->create(['name' => 'Outlet Uji']);

        $permissionIds = Permission::query()->pluck('id')->all();
        DB::table('role_permissions')->insert(array_map(fn (int $id): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $id,
        ], $permissionIds));
        DB::table('branch_assignments')->insert([
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'branch_id' => $branch->id,
        ]);
        DB::table('location_assignments')->insert([
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'location_id' => $location->id,
        ]);

        return [$owner, $tenant, $branch, $location, $membership, $role];
    }
}
