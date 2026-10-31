<?php

namespace Database\Factories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'label' => fake()->randomElement(['Rumah', 'Kantor']),
            'address' => fake()->address(),
            'latitude' => null,
            'longitude' => null,
            'is_default' => false,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
        ]);
    }
}
