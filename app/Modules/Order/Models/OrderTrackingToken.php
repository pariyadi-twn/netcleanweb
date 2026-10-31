<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Database\Factories\OrderTrackingTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'token_hash', 'expires_at', 'revoked_at', 'last_used_at', 'created_by_membership_id'])]
#[UseFactory(OrderTrackingTokenFactory::class)]
class OrderTrackingToken extends TenantModel
{
    /** @use HasFactory<OrderTrackingTokenFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'created_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'created_by_membership_id' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
