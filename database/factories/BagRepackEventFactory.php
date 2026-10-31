<?php

namespace Database\Factories;

use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagRepackEvent;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BagRepackEvent>
 */
class BagRepackEventFactory extends Factory
{
    protected $model = BagRepackEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'source_bag_id' => Bag::factory(),
            'target_bag_id' => Bag::factory(),
            'actor_membership_id' => 1,
            'allocation_snapshot' => [],
            'reason' => 'Test repack',
        ];
    }
}
