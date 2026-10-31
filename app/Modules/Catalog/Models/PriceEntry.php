<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\PriceTier;
use App\Support\Database\TenantModel;
use Database\Factories\PriceEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['price_book_id', 'service_id', 'tier', 'unit_price_rupiah', 'effective_from', 'effective_until', 'rules'])]
#[UseFactory(PriceEntryFactory::class)]
class PriceEntry extends TenantModel
{
    /** @use HasFactory<PriceEntryFactory> */
    use HasFactory;

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
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
            'price_book_id' => 'integer',
            'service_id' => 'integer',
            'tier' => PriceTier::class,
            'unit_price_rupiah' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'rules' => 'array',
        ];
    }
}
