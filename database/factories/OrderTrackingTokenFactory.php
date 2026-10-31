<?php

namespace Database\Factories;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderTrackingToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTrackingToken>
 */
class OrderTrackingTokenFactory extends Factory
{
    protected $model = OrderTrackingToken::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
            'last_used_at' => null,
            'created_by_membership_id' => null,
        ];
    }
}
