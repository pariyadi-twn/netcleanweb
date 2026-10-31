<?php

namespace Database\Factories;

use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'customer_id' => null,
            'sales_branch_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->sales_branch_id,
            'invoice_number' => fake()->unique()->bothify('INV-####-????'),
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => 0,
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ];
    }
}
