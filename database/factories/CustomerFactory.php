<?php

namespace Database\Factories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'normalized_phone' => '08'.fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'external_ref' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }
}
