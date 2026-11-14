<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UatProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_provisions_synthetic_uat_identity_data_idempotently(): void
    {
        $this->artisan('uat:provision')
            ->expectsOutputToContain('Synthetic UAT tenant and staff are ready.')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('locations', 2);
        $this->assertDatabaseCount('tenant_memberships', 4);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseHas('users', ['email' => 'owner@uat.netclean.id', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'cashier@uat.netclean.id', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'production@uat.netclean.id', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'supervisor@uat.netclean.id', 'status' => 'active']);
        $this->assertSame(['OUTLET-MAIN', 'PLANT-MAIN'], Location::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['cashier', 'owner', 'production', 'supervisor'], Role::query()->orderBy('code')->pluck('code')->all());

        $tenantPublicId = Tenant::query()->sole()->public_id;
        $permissionVersions = TenantMembership::query()->orderBy('id')->pluck('permission_version')->all();

        $this->artisan('uat:provision')
            ->expectsOutputToContain('Existing records were preserved.')
            ->doesntExpectOutputToContain('Store these generated passwords now.')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('locations', 2);
        $this->assertDatabaseCount('tenant_memberships', 4);
        $this->assertDatabaseCount('roles', 4);
        $this->assertSame($tenantPublicId, Tenant::query()->sole()->public_id);
        $this->assertSame($permissionVersions, TenantMembership::query()->orderBy('id')->pluck('permission_version')->all());
    }

    public function test_command_rejects_invalid_options_without_writing_data(): void
    {
        $this->artisan('uat:provision', ['--owner-email' => 'invalid-email'])
            ->expectsOutputToContain('Provide a valid owner email')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_command_is_disabled_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->artisan('uat:provision')
                ->expectsOutputToContain('UAT provisioning is disabled in production.')
                ->assertFailed();

            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('tenants', 0);
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }
}
