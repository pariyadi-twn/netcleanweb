<?php

namespace Database\Factories;

use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BagContent>
 */
class BagContentFactory extends Factory
{
    protected $model = BagContent::class;

    public function definition(): array
    {
        return [
            'bag_id' => Bag::factory(),
            'tenant_id' => fn (array $attributes): int => Bag::query()->findOrFail($attributes['bag_id'])->tenant_id,
            'order_id' => fn (array $attributes): int => Bag::query()->findOrFail($attributes['bag_id'])->order_id,
            'order_line_id' => fn (array $attributes): int => OrderLine::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'order_id' => $attributes['order_id'],
            ])->id,
            'physical_quantity' => '1.000',
            'handed_over_quantity' => '0.000',
            'is_active' => true,
        ];
    }
}
