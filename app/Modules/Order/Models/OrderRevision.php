<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\OrderRevisionStatus;
use App\Support\Database\TenantModel;
use Database\Factories\OrderRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'status',
    'expected_version',
    'reason',
    'decision_reason',
    'requested_by_membership_id',
    'approved_by_membership_id',
    'payload',
    'difference_rupiah',
    'current_total_rupiah',
    'invoice_adjustment_id',
    'decided_at',
    'applied_at',
])]
#[UseFactory(OrderRevisionFactory::class)]
class OrderRevision extends TenantModel
{
    /** @use HasFactory<OrderRevisionFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InvoiceAdjustment::class, 'invoice_adjustment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'status' => OrderRevisionStatus::class,
            'expected_version' => 'integer',
            'requested_by_membership_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'payload' => 'array',
            'difference_rupiah' => 'integer',
            'current_total_rupiah' => 'integer',
            'invoice_adjustment_id' => 'integer',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
