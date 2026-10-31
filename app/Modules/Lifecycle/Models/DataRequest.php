<?php

namespace App\Modules\Lifecycle\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'requested_by_membership_id',
    'type',
    'status',
    'reason',
    'target_customer_id',
    'verification_method',
    'verified_by_user_id',
    'verified_at',
    'processed_by_user_id',
    'completed_at',
    'rejection_reason',
    'result_summary',
])]
class DataRequest extends TenantModel
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'target_customer_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'requested_by_membership_id' => 'integer',
            'target_customer_id' => 'integer',
            'verified_by_user_id' => 'integer',
            'verified_at' => 'datetime',
            'processed_by_user_id' => 'integer',
            'completed_at' => 'datetime',
            'result_summary' => 'array',
        ];
    }
}
