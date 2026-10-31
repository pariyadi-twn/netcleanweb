<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceBook>
 */
class PriceBookFactory extends Factory
{
    protected $model = PriceBook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'branch_id' => null,
            'customer_id' => null,
            'is_active' => true,
        ];
    }

    public function forBranch(int $branchId): static
    {
        return $this->state(fn (): array => ['branch_id' => $branchId]);
    }
}
