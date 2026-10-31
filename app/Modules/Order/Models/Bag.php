<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\Location;
use App\Support\Database\TenantModel;
use Database\Factories\BagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'label_code', 'current_location_id', 'custody_status', 'active_transfer_line_id', 'seal_code'])]
#[UseFactory(BagFactory::class)]
class Bag extends TenantModel
{
    /** @use HasFactory<BagFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(BagContent::class);
    }

    public function repackEvents(): HasMany
    {
        return $this->hasMany(BagRepackEvent::class, 'source_bag_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'current_location_id' => 'integer',
            'active_transfer_line_id' => 'integer',
        ];
    }
}
