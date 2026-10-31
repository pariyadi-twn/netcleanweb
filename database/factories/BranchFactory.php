<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->bothify('BR-??????'),
            'name' => fake()->company(),
            'timezone' => 'Asia/Jakarta',
            'address' => fake()->address(),
            'phone' => null,
            'is_active' => true,
        ];
    }
}
