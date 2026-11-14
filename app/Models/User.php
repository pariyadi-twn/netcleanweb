<?php

namespace App\Models;

use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\MfaRecoveryCode;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\HasPublicUuid;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret_ref'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasPublicUuid;

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    /**
     * Check owner roles across active tenants while preserving the caller's RLS context.
     */
    public function requiresMfa(): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        $connection = $this->getConnection();

        return $connection->transaction(function () use ($connection): bool {
            $previous = $connection->selectOne("SELECT current_setting('app.tenant_id', true) AS tenant_id, current_setting('app.user_id', true) AS user_id, current_setting('app.identity_bootstrap', true) AS identity_bootstrap");
            $scope = app(TenantScope::class);
            $required = $scope->identity($this, function () use ($scope, $connection): bool {
                $memberships = $this->memberships()
                    ->where('tenant_memberships.status', MembershipStatus::Active->value)
                    ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active->value))
                    ->orderBy('tenant_id')
                    ->get();

                foreach ($memberships as $membership) {
                    $isOwner = $scope->forTenantId(
                        $membership->tenant_id,
                        (int) $this->getKey(),
                        fn (): bool => $membership->roles()->where('roles.code', RoleCode::Owner->value)->exists(),
                        $connection->getName(),
                    );

                    if ($isOwner) {
                        return true;
                    }
                }

                return false;
            }, $connection->getName());

            foreach ([
                'app.tenant_id' => $previous->tenant_id,
                'app.user_id' => $previous->user_id,
                'app.identity_bootstrap' => $previous->identity_bootstrap,
            ] as $key => $value) {
                $connection->select('SELECT set_config(?, ?, true)', [$key, $value ?? '']);
            }

            return $required;
        });
    }

    public function hasConfirmedMfa(): bool
    {
        return $this->mfa_confirmed_at !== null && $this->mfa_secret_ref !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret_ref' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'status' => UserStatus::class,
            'version' => 'integer',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }
}
