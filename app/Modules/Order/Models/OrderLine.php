<?php

namespace App\Modules\Order\Models;

use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Models\Service;
use App\Support\Database\TenantModel;
use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'service_id',
    'unit',
    'measured_quantity',
    'billable_quantity',
    'unit_price_rupiah',
    'line_total_rupiah',
    'service_snapshot',
    'status',
])]
#[UseFactory(OrderLineFactory::class)]
class OrderLine extends TenantModel
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'service_id' => 'integer',
            'unit' => ServiceUnit::class,
            'measured_quantity' => 'decimal:3',
            'billable_quantity' => 'decimal:3',
            'unit_price_rupiah' => 'integer',
            'line_total_rupiah' => 'integer',
            'service_snapshot' => 'array',
        ];
    }
}
