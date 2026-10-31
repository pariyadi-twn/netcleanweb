<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Modules\Identity\Models\MfaRecoveryCode;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    public function test_owner_requires_totp_and_a_recovery_code_is_single_use(): void
    {
        $owner = $this->createOwner(['password' => 'secret-password']);
        $enrollment = $this->enableMfa($owner);
        $installationId = (string) Str::uuid();

        $payload = [
            'email' => $owner->email,
            'password' => 'secret-password',
            'device_name' => 'Owner phone',
            'installation_id' => $installationId,
        ];

        $this->postJson('/api/v1/auth/tokens', $payload)->assertUnauthorized();

        $this->postJson('/api/v1/auth/tokens', [...$payload, 'mfa_code' => $this->currentMfaCode($owner)])
            ->assertCreated();

        $recoveryCode = $enrollment['recovery_codes'][0];

        $this->postJson('/api/v1/auth/tokens', [...$payload, 'mfa_code' => $recoveryCode])->assertCreated();
        $this->postJson('/api/v1/auth/tokens', [...$payload, 'mfa_code' => $recoveryCode])->assertUnauthorized();
    }

    public function test_platform_admin_requires_mfa(): void
    {
        $admin = User::factory()->platformAdmin()->create(['password' => 'secret-password']);
        $this->enableMfa($admin);

        $payload = [
            'email' => $admin->email,
            'password' => 'secret-password',
            'device_name' => 'Admin phone',
            'installation_id' => (string) Str::uuid(),
        ];

        $this->postJson('/api/v1/auth/tokens', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/tokens', [...$payload, 'mfa_code' => $this->currentMfaCode($admin)])
            ->assertCreated();
    }

    public function test_owner_without_confirmed_mfa_cannot_obtain_a_token(): void
    {
        $owner = $this->createOwner(['password' => 'secret-password']);

        $this->postJson('/api/v1/auth/tokens', [
            'email' => $owner->email,
            'password' => 'secret-password',
            'device_name' => 'Owner phone',
            'installation_id' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }

    public function test_mfa_enrollment_returns_hashed_recovery_codes_once(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $issued = $this->issueTokenFor($user);

        $start = $this->withToken($issued['token'])->postJson('/api/v1/me/mfa')->assertCreated();

        $secret = $start->json('data.secret');
        $this->assertIsString($secret);
        $this->assertStringStartsWith('otpauth://totp/', $start->json('data.provisioning_uri'));

        $code = app(TotpService::class)->currentCode($secret);

        $confirm = $this->withToken($issued['token'])->postJson('/api/v1/me/mfa/confirmation', ['code' => $code])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $codes = $confirm->json('data.recovery_codes');
        $this->assertCount(8, array_unique($codes));
        $this->assertSame(8, $user->refresh()->mfaRecoveryCodes()->count());
        $this->assertNotNull($user->mfa_confirmed_at);

        $storedHashes = MfaRecoveryCode::query()->pluck('code_hash')->all();
        $this->assertNotContains($codes[0], $storedHashes);
        $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => $codes[0]]);
    }

    public function test_recovery_codes_can_be_regenerated_only_with_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $enrollment = $this->enableMfa($user);
        $issued = $this->issueTokenFor($user);

        $this->withToken($issued['token'])->postJson('/api/v1/me/mfa/recovery-codes', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['password']]]);

        $response = $this->withToken($issued['token'])->postJson('/api/v1/me/mfa/recovery-codes', ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $newCodes = $response->json('data.recovery_codes');
        $this->assertNotSame($enrollment['recovery_codes'], $newCodes);
        $this->assertSame(8, MfaRecoveryCode::query()->count());

        $oldHashes = collect($enrollment['recovery_codes'])->map(fn (string $code) => hash('sha256', str_replace('-', '', $code)));
        $this->assertSame(0, DB::table('mfa_recovery_codes')->whereIn('code_hash', $oldHashes)->count());
    }
}
