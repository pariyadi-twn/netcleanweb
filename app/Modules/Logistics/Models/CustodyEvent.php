<?php

namespace App\Modules\Logistics\Models;

use App\Modules\Order\Models\Bag;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bag_id',
    'event_type',
    'from_location_id',
    'to_location_id',
    'handover_line_id',
    'actor_membership_id',
])]
class CustodyEvent extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function bag(): BelongsTo
    {
        return $this->belongsTo(Bag::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'bag_id' => 'integer',
            'from_location_id' => 'integer',
            'to_location_id' => 'integer',
            'handover_line_id' => 'integer',
            'actor_membership_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
