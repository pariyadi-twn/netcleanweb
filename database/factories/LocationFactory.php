<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => null,
            'code' => fake()->unique()->bothify('LOC-??????'),
            'name' => fake()->city(),
            'type' => 'plant',
            'parent_location_id' => null,
            'is_active' => true,
        ];
    }

    public function atBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'type' => 'outlet']);
    }
}
