<?php

namespace Database\Factories;

use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\InvoiceAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceAdjustment>
 */
class InvoiceAdjustmentFactory extends Factory
{
    protected $model = InvoiceAdjustment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'tenant_id' => fn (array $attributes): int => Invoice::query()->findOrFail($attributes['invoice_id'])->tenant_id,
            'order_revision_id' => null,
            'kind' => 'debit_note',
            'amount_rupiah' => 1000,
            'reason' => 'Test adjustment',
            'requested_by_membership_id' => 1,
            'approved_by_membership_id' => 1,
        ];
    }
}
