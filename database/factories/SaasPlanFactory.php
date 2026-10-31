<?php

namespace Database\Factories;

use App\Modules\Saas\Models\SaasPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasPlan>
 */
class SaasPlanFactory extends Factory
{
    protected $model = SaasPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'revision' => 1,
            'name' => fake()->words(2, true),
            'base_price_rupiah' => 0,
            'features' => [
                'limits' => ['staff' => 5, 'branches' => 1],
                'features' => ['exports' => false],
            ],
            'is_active' => true,
        ];
    }
}
