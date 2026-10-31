<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    public function test_login_issues_device_bound_token_with_explicit_expiry(): void
    {
        $this->travelTo('2026-10-31 12:00:00');

        $user = User::factory()->create(['password' => 'secret-password']);
        $installationId = (string) Str::uuid();

        $response = $this->withHeader('X-Correlation-ID', 'correlation-123')
            ->postJson('/api/v1/auth/tokens', [
                'email' => Str::upper($user->email),
                'password' => 'secret-password',
                'device_name' => 'Pixel 9',
                'installation_id' => $installationId,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-01T00:00:00Z')
            ->assertJsonPath('meta.correlation_id', 'correlation-123')
            ->assertHeader('X-Correlation-ID', 'correlation-123');

        $plainTextToken = $response->json('data.token');
        [$tokenId, $tokenSecret] = explode('|', $plainTextToken, 2);
        $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);
        $device = Device::query()->firstOrFail();

        $this->assertSame(hash('sha256', $tokenSecret), $token->token);
        $this->assertNotSame($plainTextToken, $token->token);
        $this->assertSame($device->id, $token->device_id);
        $this->assertSame(hash('sha256', $installationId), $device->installation_hash);
        $this->assertSame('Pixel 9', $device->label);
        $this->assertSame($device->public_id, $response->json('data.device_id'));
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_token_request_rejects_missing_and_unknown_fields(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/tokens', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'Pixel 9',
            'installation_id' => (string) Str::uuid(),
            'role' => 'owner',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field_errors.role.0', 'This field is not allowed.')
            ->assertJsonStructure(['error' => ['field_errors'], 'meta' => ['correlation_id']]);

        $this->postJson('/api/v1/auth/tokens', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'Pixel 9',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['installation_id']]]);
    }

    public function test_login_failures_do_not_reveal_whether_an_account_exists(): void
    {
        $active = User::factory()->create(['password' => 'secret-password']);
        $disabled = User::factory()->disabled()->create(['password' => 'secret-password']);
        $installationId = (string) Str::uuid();

        $responses = [
            $this->postJson('/api/v1/auth/tokens', [
                'email' => 'missing@example.com',
                'password' => 'secret-password',
                'device_name' => 'Device',
                'installation_id' => $installationId,
            ]),
            $this->postJson('/api/v1/auth/tokens', [
                'email' => $active->email,
                'password' => 'wrong-password',
                'device_name' => 'Device',
                'installation_id' => $installationId,
            ]),
            $this->postJson('/api/v1/auth/tokens', [
                'email' => $disabled->email,
                'password' => 'secret-password',
                'device_name' => 'Device',
                'installation_id' => $installationId,
            ]),
        ];

        foreach ($responses as $response) {
            $response->assertUnauthorized()
                ->assertJsonPath('error.code', 'UNAUTHENTICATED')
                ->assertJsonPath('error.message', 'Unauthenticated.');
        }

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_only_own_active_device_sessions_are_listed_and_revocation_applies_on_next_request(): void
    {
        $user = User::factory()->create();
        $first = $this->issueTokenFor($user, (string) Str::uuid(), 'Phone');
        $second = $this->issueTokenFor($user, (string) Str::uuid(), 'Tablet');
        $other = User::factory()->create();
        $otherToken = $this->issueTokenFor($other, (string) Str::uuid(), 'Other phone');

        $response = $this->withToken($first['token'])->getJson('/api/v1/auth/tokens');

        $response->assertOk()->assertJsonCount(2, 'data');

        $deviceIds = collect($response->json('data'))->pluck('device_id')->all();
        $this->assertContains($first['device']->public_id, $deviceIds);
        $this->assertContains($second['device']->public_id, $deviceIds);
        $this->assertNotContains($otherToken['device']->public_id, $deviceIds);

        $this->withToken($first['token'])
            ->deleteJson('/api/v1/auth/tokens/'.$first['device']->public_id)
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($first['token'])->getJson('/api/v1/auth/tokens')->assertUnauthorized();
        $this->assertNotNull($first['device']->refresh()->revoked_at);
        $this->assertSame(0, $first['device']->tokens()->count());

        $this->withToken($second['token'])->getJson('/api/v1/auth/tokens')->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($second['token'])
            ->deleteJson('/api/v1/auth/tokens/'.$otherToken['device']->public_id)
            ->assertNotFound();

        $this->withToken($otherToken['token'])->getJson('/api/v1/auth/tokens')->assertOk();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $issued = $this->issueTokenFor($user);

        $this->travelTo(now()->addHours(13));

        $this->withToken($issued['token'])->getJson('/api/v1/auth/tokens')->assertUnauthorized();
    }
}
