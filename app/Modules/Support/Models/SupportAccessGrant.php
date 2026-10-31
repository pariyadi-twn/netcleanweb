<?php

namespace App\Modules\Support\Models;

use App\Models\User;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'platform_user_id',
    'approved_by_membership_id',
    'case_reference',
    'reason',
    'allowed_actions',
    'expires_at',
    'revoked_at',
    'revoked_by_membership_id',
    'last_used_at',
    'use_count',
])]
class SupportAccessGrant extends TenantModel
{
    public const READ_ACTIONS = [
        'branches.view',
        'locations.view',
        'memberships.view',
        'customers.view',
        'catalog.view',
        'orders.view',
        'cash.view',
        'payments.view',
        'reports.view',
        'audit.view',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'platform_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'revoked_by_membership_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'platform_user_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'allowed_actions' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoked_by_membership_id' => 'integer',
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }
}
