<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Support\Database\TenantModel;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([])]
#[UseFactory(TenantMembershipFactory::class)]
class TenantMembership extends TenantModel
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'membership_id', 'role_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_assignments', 'membership_id', 'branch_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_assignments', 'membership_id', 'location_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'status' => MembershipStatus::class, 'permission_version' => 'integer'];
    }
}
