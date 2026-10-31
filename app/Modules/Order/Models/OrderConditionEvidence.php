<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Database\Factories\OrderConditionEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'notes', 'recorded_by_membership_id', 'recorded_at'])]
#[UseFactory(OrderConditionEvidenceFactory::class)]
class OrderConditionEvidence extends TenantModel
{
    /** @use HasFactory<OrderConditionEvidenceFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'recorded_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'recorded_by_membership_id' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
