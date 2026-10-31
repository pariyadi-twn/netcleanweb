<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_login_attempts_return_429_with_retry_after(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $payload = [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'Device',
            'installation_id' => (string) Str::uuid(),
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/tokens', $payload)->assertUnauthorized();
        }

        $response = $this->postJson('/api/v1/auth/tokens', $payload);

        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonStructure(['error' => ['field_errors'], 'meta' => ['correlation_id']]);
    }
}
