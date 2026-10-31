<?php

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Facades\DB;

final class CreateCustomer
{
    public function __construct(
        private readonly NormalizePhoneNumber $phones,
        private readonly CreateCustomerAddress $addresses,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $tenantId, array $attributes, ?User $actor = null): Customer
    {
        $normalizedPhone = $this->phones->optional($attributes['phone'] ?? null);

        return DB::transaction(function () use ($tenantId, $attributes, $normalizedPhone): Customer {
            $customer = new Customer([
                'name' => $attributes['name'],
                'normalized_phone' => $normalizedPhone,
                'email' => $attributes['email'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            $customer->tenant_id = $tenantId;
            $customer->save();

            foreach (array_values($attributes['addresses'] ?? []) as $index => $address) {
                $this->addresses->handle($customer, $address, $index === 0);
            }

            return $customer->load('addresses');
        });
    }
}
