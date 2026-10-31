<?php

namespace Database\Factories;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\ReceiptPrintAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptPrintAttempt>
 */
class ReceiptPrintAttemptFactory extends Factory
{
    protected $model = ReceiptPrintAttempt::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'actor_membership_id' => 1,
            'status' => 'printed',
            'receipt_hash' => hash('sha256', fake()->uuid()),
            'error_code' => null,
        ];
    }
}
