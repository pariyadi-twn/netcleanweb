<?php

namespace Database\Factories;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Models\Branch;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): int => Branch::query()->findOrFail($attributes['received_branch_id'])->tenant_id,
            'received_branch_id' => Branch::factory(),
            'cash_session_id' => CashSession::factory(),
            'method' => 'cash',
            'amount_rupiah' => 30000,
            'cash_received_rupiah' => 50000,
            'change_rupiah' => 20000,
            'unallocated_rupiah' => 0,
            'received_at' => now(),
        ];
    }
}
