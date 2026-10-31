<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'sales_branch_id' => Branch::factory(),
            'tenant_id' => fn (array $attributes): int => Branch::query()->findOrFail($attributes['sales_branch_id'])->tenant_id,
            'customer_id' => null,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => fn (array $attributes): int => $attributes['sales_branch_id'],
            'instructions' => null,
            'request_hash' => hash('sha256', (string) fake()->uuid()),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 0,
            'pricing_snapshot' => [],
            'consumed_at' => null,
        ];
    }
}
