<?php

namespace Database\Factories;

use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'tenant_id' => fn (array $attributes): int => Invoice::query()->findOrFail($attributes['invoice_id'])->tenant_id,
            'order_line_id' => null,
            'kind' => 'service',
            'description' => fake()->words(3, true),
            'unit' => 'kg',
            'quantity' => '1.000',
            'unit_price_rupiah' => 8000,
            'amount_rupiah' => 8000,
        ];
    }
}
