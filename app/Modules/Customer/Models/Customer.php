<?php

namespace App\Modules\Customer\Models;

use App\Modules\Identity\Models\Tenant;
use App\Support\Database\TenantModel;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_phone', 'email', 'external_ref', 'notes', 'is_active'])]
#[UseFactory(CustomerFactory::class)]
class Customer extends TenantModel
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'is_active' => 'boolean'];
    }
}
