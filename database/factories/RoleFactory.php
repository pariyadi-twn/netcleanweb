<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->jobTitle(),
            'is_system' => false,
        ];
    }
}
