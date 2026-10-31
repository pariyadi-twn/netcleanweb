<?php

namespace App\Modules\Customer\Models;

use App\Modules\Identity\Models\Tenant;
use App\Support\Database\TenantModel;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'label', 'address', 'latitude', 'longitude', 'is_default'])]
#[UseFactory(CustomerAddressFactory::class)]
class CustomerAddress extends TenantModel
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }
}
