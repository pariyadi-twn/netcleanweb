<?php

namespace App\Modules\Logistics\Models;

use App\Modules\Order\Models\BagContent;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['handover_id', 'order_id', 'bag_content_id', 'quantity'])]
class HandoverLine extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(BagContent::class, 'bag_content_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'handover_id' => 'integer',
            'order_id' => 'integer',
            'bag_content_id' => 'integer',
            'quantity' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }
}
