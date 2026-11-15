<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_page_is_available(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Buat akun pemilik')
            ->assertSee('Buat akun dan organisasi')
            ->assertDontSee('name="timezone"', false);
    }

    public function test_owner_registration_provisions_a_tenant_and_redirects_to_context_selection(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('tenants.select'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'pemilik@example.test')->sole();
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_platform_admin);

        $tenant = Tenant::query()->where('name', 'Laundry Baru')->sole();
        $this->assertSame('Asia/Jakarta', $tenant->timezone);
        $this->assertSame('Asia/Jakarta', Branch::query()->where('tenant_id', $tenant->id)->sole()->timezone);
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->sole();
        $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'owner')->sole();

        $this->assertSame('active', $membership->status->value);
        $this->assertTrue($role->is_system);
        $this->assertGreaterThan(0, $role->permissions()->count());
        $this->assertFalse($user->refresh()->requiresMfa());
    }

    public function test_owner_registration_requires_mfa_when_enabled(): void
    {
        config(['identity.mfa.mode' => 'required']);

        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('mfa.setup'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'pemilik@example.test')->sole();
        $this->assertTrue($user->requiresMfa());
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'pemilik@example.test']);

        $this->post('/register', $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('tenants', 0);
    }

    /** @return array<string, string> */
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
