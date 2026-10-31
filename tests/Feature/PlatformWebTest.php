<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformWebTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_dashboard_requires_platform_admin_and_mfa(): void
    {
        $normal = User::factory()->create();
        $this->actingAs($normal)->get('/platform')->assertForbidden();

        $platform = User::factory()->platformAdmin()->create([
            'mfa_confirmed_at' => now(),
            'mfa_secret_ref' => 'test-secret',
        ]);

        $this->actingAs($platform)
            ->get('/platform')
            ->assertOk()
            ->assertSee('NetClean Platform')
            ->assertSee('Konteks: platform')
            ->assertSee('Permintaan data');
    }
}
