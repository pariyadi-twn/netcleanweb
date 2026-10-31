<?php

namespace Database\Factories;

use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->tenant_id,
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'amount_rupiah' => 30000,
        ];
    }
}
