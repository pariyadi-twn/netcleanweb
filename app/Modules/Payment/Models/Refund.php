<?php

namespace App\Modules\Payment\Models;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Payment\Enums\RefundStatus;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_id',
    'cash_session_id',
    'amount_rupiah',
    'status',
    'external_ref',
    'requested_by_membership_id',
    'approved_by_membership_id',
    'reason',
    'succeeded_at',
])]
class Refund extends TenantModel
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RefundAllocation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RefundEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'payment_id' => 'integer',
            'cash_session_id' => 'integer',
            'amount_rupiah' => 'integer',
            'status' => RefundStatus::class,
            'requested_by_membership_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'succeeded_at' => 'datetime',
        ];
    }
}
