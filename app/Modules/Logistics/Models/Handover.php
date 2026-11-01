<?php

namespace App\Modules\Logistics\Models;

use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\CreditApproval;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'location_id',
    'actor_membership_id',
    'recipient_name',
    'verification_method',
    'credit_approval_id',
    'completed_at',
])]
class Handover extends TenantModel
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    public function creditApproval(): BelongsTo
    {
        return $this->belongsTo(CreditApproval::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HandoverLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'location_id' => 'integer',
            'actor_membership_id' => 'integer',
            'credit_approval_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
