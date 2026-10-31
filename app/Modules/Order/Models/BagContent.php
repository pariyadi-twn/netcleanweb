<?php

namespace App\Modules\Order\Models;

use App\Support\Database\TenantModel;
use Database\Factories\BagContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'bag_id', 'order_line_id', 'physical_quantity', 'handed_over_quantity', 'is_active'])]
#[UseFactory(BagContentFactory::class)]
class BagContent extends TenantModel
{
    /** @use HasFactory<BagContentFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bag(): BelongsTo
    {
        return $this->belongsTo(Bag::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'bag_id' => 'integer',
            'order_line_id' => 'integer',
            'physical_quantity' => 'decimal:3',
            'handed_over_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
