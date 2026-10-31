<?php

namespace App\Modules\Order\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\FulfillmentMethod;
use App\Modules\Order\Enums\OrderStatus;
use App\Support\Database\TenantModel;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'quote_id',
    'customer_id',
    'sales_branch_id',
    'pickup_branch_id',
    'accepted_by_membership_id',
    'order_number',
    'operational_status',
    'fulfillment_method',
    'due_at_original',
    'due_at_current',
    'customer_snapshot',
    'accepted_at',
])]
#[UseFactory(OrderFactory::class)]
class Order extends TenantModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sales_branch_id');
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'accepted_by_membership_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function bags(): HasMany
    {
        return $this->hasMany(Bag::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'quote_id' => 'integer',
            'customer_id' => 'integer',
            'sales_branch_id' => 'integer',
            'pickup_branch_id' => 'integer',
            'accepted_by_membership_id' => 'integer',
            'operational_status' => OrderStatus::class,
            'fulfillment_method' => FulfillmentMethod::class,
            'due_at_original' => 'datetime',
            'due_at_current' => 'datetime',
            'customer_snapshot' => 'array',
            'accepted_at' => 'datetime',
        ];
    }
}
