<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Identity\Actions\BeginMfaEnrollment;
use App\Modules\Identity\Actions\ConfirmMfaEnrollment;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Support\Str;

trait CreatesIdentityUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createOwner(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $tenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create([
            'code' => RoleCode::Owner->value,
            'name' => 'Owner',
            'is_system' => true,
        ]);

        $membership->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        return $user;
    }

    /**
     * @return array{secret: string, recovery_codes: list<string>}
     */
    protected function enableMfa(User $user): array
    {
        $enrollment = app(BeginMfaEnrollment::class)->handle($user);
        $codes = app(ConfirmMfaEnrollment::class)->handle(
            $user->refresh(),
            app(TotpService::class)->currentCode($enrollment['secret']),
        );

        return ['secret' => $enrollment['secret'], 'recovery_codes' => $codes];
    }

    protected function currentMfaCode(User $user): string
    {
        return app(TotpService::class)->currentCode((string) $user->refresh()->mfa_secret_ref);
    }

    /**
     * @return array{token: string, device: Device}
     */
    protected function issueTokenFor(User $user, ?string $installationId = null, string $deviceName = 'Test Device'): array
    {
        $result = app(IssueDeviceToken::class)->handle(
            $user,
            $installationId ?? (string) Str::uuid(),
            $deviceName,
        );

        return ['token' => $result['token']->plainTextToken, 'device' => $result['device']];
    }
}
