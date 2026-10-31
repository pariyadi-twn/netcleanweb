<?php

namespace Database\Factories;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'tenant_id' => fn (array $attributes): int => Quote::query()->findOrFail($attributes['quote_id'])->tenant_id,
            'customer_id' => null,
            'sales_branch_id' => fn (array $attributes): int => Quote::query()->findOrFail($attributes['quote_id'])->sales_branch_id,
            'pickup_branch_id' => fn (array $attributes): int => Quote::query()->findOrFail($attributes['quote_id'])->pickup_branch_id,
            'accepted_by_membership_id' => fn (array $attributes): int => TenantMembership::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'order_number' => fake()->unique()->bothify('ORD-####-????'),
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'Walk-in'],
            'accepted_at' => now(),
        ];
    }
}
