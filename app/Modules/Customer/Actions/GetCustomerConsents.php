<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use Illuminate\Support\Collection;

final class GetCustomerConsents
{
    /**
     * @return Collection<int, CustomerConsent>
     */
    public function handle(Customer $customer): Collection
    {
        return CustomerConsent::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->with('customer')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (CustomerConsent $consent): string => $consent->purpose->value.'|'.$consent->channel->value)
            ->sortBy(fn (CustomerConsent $consent): string => $consent->purpose->value.'|'.$consent->channel->value)
            ->values();
    }
}
