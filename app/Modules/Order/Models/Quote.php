<?php

namespace App\Modules\Order\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Order\Enums\FulfillmentMethod;
use App\Support\Database\TenantModel;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_branch_id',
    'customer_id',
    'fulfillment_method',
    'pickup_branch_id',
    'instructions',
    'request_hash',
    'expires_at',
    'due_at',
    'total_rupiah',
    'pricing_snapshot',
    'consumed_at',
])]
#[UseFactory(QuoteFactory::class)]
class Quote extends TenantModel
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function salesBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sales_branch_id');
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function discountApproval(): HasOne
    {
        return $this->hasOne(DiscountApproval::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'sales_branch_id' => 'integer',
            'customer_id' => 'integer',
            'pickup_branch_id' => 'integer',
            'fulfillment_method' => FulfillmentMethod::class,
            'expires_at' => 'datetime',
            'due_at' => 'datetime',
            'total_rupiah' => 'integer',
            'pricing_snapshot' => 'array',
            'consumed_at' => 'datetime',
        ];
    }
}
