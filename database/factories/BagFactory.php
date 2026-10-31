<?php

namespace Database\Factories;

use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bag>
 */
class BagFactory extends Factory
{
    protected $model = Bag::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'label_code' => 'BAG-'.fake()->unique()->bothify('????####'),
            'current_location_id' => null,
            'custody_status' => 'at_location',
            'active_transfer_line_id' => null,
            'seal_code' => null,
        ];
    }
}
