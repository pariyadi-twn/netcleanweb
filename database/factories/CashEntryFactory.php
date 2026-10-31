<?php

namespace Database\Factories;

use App\Modules\Cash\Models\CashEntry;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    protected $model = CashEntry::class;

    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'tenant_id' => fn (array $attributes): int => CashSession::query()->findOrFail($attributes['cash_session_id'])->tenant_id,
            'kind' => 'payment_in',
            'amount_rupiah' => 500000,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
