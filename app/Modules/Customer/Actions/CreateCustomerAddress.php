<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class CreateCustomerAddress
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Customer $customer, array $attributes, bool $forceDefault = false): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $attributes, $forceDefault): CustomerAddress {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $address = new CustomerAddress([
                'label' => $attributes['label'],
                'address' => $attributes['address'],
                'latitude' => $attributes['latitude'] ?? null,
                'longitude' => $attributes['longitude'] ?? null,
            ]);

            $address->tenant_id = $customer->tenant_id;
            $address->customer_id = $customer->id;

            $isDefault = $forceDefault || (bool) ($attributes['is_default'] ?? false);

            if (! $isDefault && ! $customer->addresses()->exists()) {
                $isDefault = true;
            }

            if ($isDefault) {
                CustomerAddress::query()
                    ->where('tenant_id', $customer->tenant_id)
                    ->where('customer_id', $customer->id)
                    ->update(['is_default' => false]);
            }

            $address->is_default = $isDefault;
            $address->save();

            return $address;
        });
    }
}
