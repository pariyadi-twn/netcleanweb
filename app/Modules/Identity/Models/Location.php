<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\LocationType;
use App\Support\Database\TenantModel;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'name', 'is_active'])]
#[UseFactory(LocationFactory::class)]
class Location extends TenantModel
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_location_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_location_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'type' => LocationType::class, 'is_active' => 'boolean', 'branch_id' => 'integer', 'parent_location_id' => 'integer'];
    }
}
