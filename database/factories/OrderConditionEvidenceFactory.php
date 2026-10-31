<?php

namespace Database\Factories;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderConditionEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderConditionEvidence>
 */
class OrderConditionEvidenceFactory extends Factory
{
    protected $model = OrderConditionEvidence::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'notes' => fake()->sentence(),
            'recorded_by_membership_id' => TenantMembership::factory(),
            'recorded_at' => now(),
        ];
    }
}
