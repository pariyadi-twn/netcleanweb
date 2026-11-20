<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ChannelSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_web_state_changing_request_requires_a_csrf_token_when_enforced(): void
    {
        $this->app->instance('env', 'production');
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->get('/login')->assertOk();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertStatus(419);
        $this->assertGuest();

        $this->post('/login', [
            '_token' => session()->token(),
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_cors_allows_only_the_configured_origin(): void
    {
        config(['cors.allowed_origins' => ['https://console.example', 'https://admin.example']]);

        $this->withHeaders(['Origin' => 'https://console.example'])
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://console.example')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_api_exception_response_redacts_internal_details(): void
    {
        Route::middleware('api')->get('/api/v1/t046/error', function (): never {
            throw new RuntimeException('database password leaked');
        });

        $this->getJson('/api/v1/t046/error')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR')
            ->assertJsonPath('error.message', 'An unexpected error occurred.')
            ->assertJsonMissing(['message' => 'database password leaked'])
            ->assertDontSee('database password leaked');
    }
}
