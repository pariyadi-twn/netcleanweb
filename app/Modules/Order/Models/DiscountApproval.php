<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\DiscountApprovalStatus;
use App\Support\Database\TenantModel;
use Database\Factories\DiscountApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quote_id',
    'percent',
    'requested_by_membership_id',
    'approved_by_membership_id',
    'status',
    'reason',
    'decision_reason',
    'decided_at',
])]
#[UseFactory(DiscountApprovalFactory::class)]
class DiscountApproval extends TenantModel
{
    /** @use HasFactory<DiscountApprovalFactory> */
    use HasFactory;

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'quote_id' => 'integer',
            'percent' => 'integer',
            'requested_by_membership_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'status' => DiscountApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
