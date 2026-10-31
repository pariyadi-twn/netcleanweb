<?php

namespace App\Modules\Lifecycle\Actions;

use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Facades\DB;

final class AnonymizeCustomer
{
    /**
     * @return array<string, int>
     */
    public function handle(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $locked = Customer::query()
                ->where('tenant_id', $customer->tenant_id)
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'name' => 'Deleted Customer',
                'normalized_phone' => null,
                'email' => null,
                'external_ref' => null,
                'notes' => null,
                'is_active' => false,
                'version' => $locked->version + 1,
            ])->save();

            $addresses = $locked->addresses()->update([
                'label' => 'Deleted',
                'address' => 'Deleted',
                'latitude' => null,
                'longitude' => null,
            ]);

            return ['addresses' => $addresses];
        });
    }
}
