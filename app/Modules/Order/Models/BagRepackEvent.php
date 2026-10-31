<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\HasPublicUuid;
use Database\Factories\BagRepackEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'source_bag_id', 'target_bag_id', 'actor_membership_id', 'allocation_snapshot', 'reason'])]
#[UseFactory(BagRepackEventFactory::class)]
class BagRepackEvent extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sourceBag(): BelongsTo
    {
        return $this->belongsTo(Bag::class, 'source_bag_id');
    }

    public function targetBag(): BelongsTo
    {
        return $this->belongsTo(Bag::class, 'target_bag_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'order_id' => 'integer',
            'source_bag_id' => 'integer',
            'target_bag_id' => 'integer',
            'actor_membership_id' => 'integer',
            'allocation_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
