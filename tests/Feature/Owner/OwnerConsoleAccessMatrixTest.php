<?php

namespace Tests\Feature\Owner;

use App\Models\User;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OwnerConsoleAccessMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['identity.mfa.mode' => 'disabled']);
        $this->artisan('uat:provision')->assertSuccessful();
    }

    public function test_owner_and_supervisor_can_access_console_but_cashier_and_operator_cannot(): void
    {
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();
        $paths = ['/orders', '/production', '/finance', '/reports', '/settings'];

        foreach (['owner@uat.netclean.id', 'supervisor@uat.netclean.id'] as $email) {
            $user = User::query()->where('email', $email)->sole();
            $this->flushSession();
            foreach ($paths as $path) {
                $this->actingAs($user)
                    ->withSession(['tenant.public_id' => $tenant->public_id])
                    ->get($path)
                    ->assertOk();
            }
        }

        foreach (['cashier@uat.netclean.id', 'production@uat.netclean.id'] as $email) {
            $user = User::query()->where('email', $email)->sole();
            $this->flushSession();
            $this->actingAs($user)
                ->withSession(['tenant.public_id' => $tenant->public_id])
                ->get('/orders')
                ->assertForbidden();
        }
    }
}
