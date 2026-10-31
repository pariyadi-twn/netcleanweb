<?php

namespace Database\Factories;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRevision>
 */
class OrderRevisionFactory extends Factory
{
    protected $model = OrderRevision::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'status' => 'pending',
            'expected_version' => 1,
            'reason' => 'Test revision',
            'requested_by_membership_id' => 1,
            'approved_by_membership_id' => null,
            'payload' => [],
            'difference_rupiah' => 1000,
            'current_total_rupiah' => 10000,
            'invoice_adjustment_id' => null,
            'decided_at' => null,
            'applied_at' => null,
        ];
    }
}
