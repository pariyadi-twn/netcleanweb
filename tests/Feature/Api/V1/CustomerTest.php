<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_creation_normalizes_phone_and_keeps_a_default_address(): void
    {
        [$tenant, , $token] = $this->scenario();

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/customers', [
                'name' => 'Siti Aminah',
                'phone' => '+62 812-3456-7890',
                'email' => 'siti@example.com',
                'notes' => 'Pelanggan langganan',
                'addresses' => [[
                    'label' => 'Rumah',
                    'address' => 'Jl. Merdeka 1',
                    'is_default' => true,
                ]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Siti Aminah')
            ->assertJsonPath('data.normalized_phone', '081234567890')
            ->assertJsonPath('data.addresses.0.label', 'Rumah')
            ->assertJsonPath('data.addresses.0.is_default', true);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'normalized_phone' => '081234567890',
        ]);
    }

    public function test_search_finds_by_phone_and_name_and_cursor_paginates(): void
    {
        [$tenant, , $token] = $this->scenario();

        Customer::factory()->for($tenant)->create(['name' => 'Alpha', 'normalized_phone' => '081111111111']);
        Customer::factory()->for($tenant)->create(['name' => 'Bravo', 'normalized_phone' => '082222222222']);
        Customer::factory()->for($tenant)->create(['name' => 'Charlie', 'normalized_phone' => '083333333333']);
        Customer::factory()->create(['name' => 'Other tenant', 'normalized_phone' => '084444444444']);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers?q=+62+822-2222-2222')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bravo');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers?q=char')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Charlie');

        $firstPage = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers?per_page=2&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Charlie');
    }

    public function test_duplicate_candidates_are_limited_to_the_current_tenant(): void
    {
        [$tenant, , $token] = $this->scenario();

        $first = Customer::factory()->for($tenant)->create([
            'name' => 'Budi',
            'normalized_phone' => '085555555555',
        ]);
        $second = Customer::factory()->for($tenant)->create([
            'name' => 'Budi',
            'normalized_phone' => '085555555555',
        ]);
        Customer::factory()->create(['name' => 'Budi other', 'normalized_phone' => '085555555555']);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/duplicates?phone=6285555555555')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($first->public_id, $ids);
        $this->assertContains($second->public_id, $ids);
    }

    public function test_customer_detail_and_address_default_are_isolated_and_consistent(): void
    {
        [$tenant, , $token] = $this->scenario();

        $customer = Customer::factory()->for($tenant)->create(['name' => 'Dewi']);
        $firstAddress = CustomerAddress::factory()->forCustomer($customer)->create([
            'label' => 'Rumah',
            'is_default' => true,
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/customers/'.$customer->public_id.'/addresses', [
                'label' => 'Kantor',
                'address' => 'Jl. Sudirman 2',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Kantor')
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($firstAddress->refresh()->is_default);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id)
            ->assertOk()
            ->assertJsonCount(2, 'data.addresses');

        $otherCustomer = Customer::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$otherCustomer->public_id)
            ->assertNotFound();
    }

    public function test_invalid_phone_and_viewer_permissions_are_rejected(): void
    {
        [$tenant, $user, $token, $role] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/customers', ['name' => 'Invalid', 'phone' => '123'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['phone']]]);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::CustomersView->value)->sole()->id],
            $user,
            'Downgrade to customer viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers?q=Alpha')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/customers', ['name' => 'Denied'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @return array{Tenant, User, string, Role}
     */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'customer-admin', 'name' => 'Customer admin']);

        $permissionIds = collect([PermissionCode::CustomersView, PermissionCode::CustomersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [], [], $user, 'Bootstrap customer admin');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Customer test device');

        return [$tenant, $user, $issued['token']->plainTextToken, $role];
    }
}
