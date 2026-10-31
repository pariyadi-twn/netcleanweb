<?php

namespace App\Modules\Identity\Models;

use App\Support\Database\TenantModel;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'timezone', 'address', 'phone', 'is_active'])]
#[UseFactory(BranchFactory::class)]
class Branch extends TenantModel
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'is_active' => 'boolean'];
    }
}
