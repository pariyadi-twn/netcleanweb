<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Models\BranchCalendar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StarterDefaultsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_provisions_a_ready_to_use_small_laundry_tenant(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('tenants.select'));

        $tenant = Tenant::query()->where('name', 'Laundry Baru')->sole();
        $user = User::query()->where('email', 'pemilik@example.test')->sole();
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->sole();

        $this->assertSame('simple', $tenant->production_mode->value);
        $this->assertFalse($tenant->require_payment_before_handover);
        $this->assertSame(1, $membership->locations()->count());
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'OUTLET',
            'type' => 'outlet',
        ]);
        $this->assertDatabaseHas('cash_registers', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'KASIR-1',
        ]);
        $this->assertSame(8, BranchCalendar::query()->where('tenant_id', $tenant->id)->count());

        $workflow = WorkflowTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->sole();
        $this->assertSame('standar', $workflow->code);
        $this->assertSame(3, $workflow->steps()->count());

        $services = Service::query()->where('tenant_id', $tenant->id)->orderBy('code')->get();
        $this->assertSame(['bed-cover', 'kiloan'], $services->pluck('code')->all());
        $this->assertTrue($services->every(fn (Service $service): bool => $service->is_active));
        $this->assertSame(2, PriceEntry::query()->where('tenant_id', $tenant->id)->count());

        $roles = Role::query()->where('tenant_id', $tenant->id)->orderBy('code')->get();
        $this->assertSame(['kasir', 'operator', 'owner'], $roles->pluck('code')->all());
        $this->assertGreaterThan(0, $roles->firstWhere('code', 'kasir')->permissions()->count());
        $this->assertGreaterThan(0, $roles->firstWhere('code', 'operator')->permissions()->count());
    }

    public function test_owner_can_add_locations_and_cash_registers_from_the_console(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('tenants.select'));

        $tenant = Tenant::query()->where('name', 'Laundry Baru')->sole();
        $user = User::query()->where('email', 'pemilik@example.test')->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->sole();
        $session = ['tenant.public_id' => $tenant->public_id];

        $this->actingAs($user)->withSession($session)
            ->post('/branches/'.$branch->public_id.'/locations', [
                'code' => 'PRODUKSI',
                'name' => 'Ruang Produksi',
                'type' => 'plant',
            ])->assertRedirect(route('branches.edit', $branch));

        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'PRODUKSI',
            'type' => 'plant',
        ]);
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->sole();
        $this->assertTrue($membership->locations()->where('code', 'PRODUKSI')->exists());

        $this->actingAs($user)->withSession($session)
            ->post('/finance/cash/registers', [
                'branch_id' => $branch->public_id,
                'code' => 'KASIR-2',
            ])->assertRedirect(route('finance.cash.index'));

        $this->assertDatabaseHas('cash_registers', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'KASIR-2',
        ]);
        $this->assertSame(2, CashRegister::query()->where('tenant_id', $tenant->id)->count());

        $this->actingAs($user)->withSession($session)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Siapkan usaha Anda')
            ->assertSee('Akun staf tambahan');

        $kasirRole = Role::query()->where('tenant_id', $tenant->id)->where('code', 'kasir')->sole();
        $locationIds = Location::query()->where('tenant_id', $tenant->id)->pluck('id')->all();

        $this->actingAs($user)->withSession($session)
            ->post('/staff', [
                'name' => 'Kasir Baru',
                'email' => 'kasir@example.test',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
                'role_id' => $kasirRole->id,
                'branch_ids' => [$branch->id],
                'location_ids' => $locationIds,
            ])->assertRedirect(route('staff.index'));

        $staff = User::query()->where('email', 'kasir@example.test')->sole();
        $staffMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $staff->id)
            ->sole();
        $this->assertTrue($staffMembership->roles()->where('roles.id', $kasirRole->id)->exists());
        $this->assertSame(count($locationIds), $staffMembership->locations()->count());

        $this->actingAs($user)->withSession($session)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Siapkan usaha Anda');
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'name' => 'Pemilik Baru',
            'email' => 'pemilik@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'organization_name' => 'Laundry Baru',
            'branch_name' => 'Cabang Utama',
            'branch_address' => 'Jl. Baru No. 1',
            'branch_phone' => '081234567892',
            'terms' => '1',
        ];
    }
}
