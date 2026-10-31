<?php

namespace App\Models;

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

    public function requiresMfa(): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->memberships()
            ->where('tenant_memberships.status', MembershipStatus::Active->value)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active->value))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('membership_roles')
                    ->join('roles', 'roles.id', '=', 'membership_roles.role_id')
                    ->whereColumn('membership_roles.membership_id', 'tenant_memberships.id')
                    ->whereColumn('membership_roles.tenant_id', 'tenant_memberships.tenant_id')
                    ->where('roles.code', RoleCode::Owner->value);
            })
            ->exists();
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
