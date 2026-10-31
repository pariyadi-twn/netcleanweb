<?php

namespace Database\Factories;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'event_type' => 'accepted',
            'actor_membership_id' => fn (array $attributes): int => TenantMembership::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'reason' => null,
            'payload' => [],
        ];
    }
}
