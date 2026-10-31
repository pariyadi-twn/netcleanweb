<?php

namespace Database\Factories;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'tenant_id' => fn (array $attributes): int => CashRegister::query()->findOrFail($attributes['cash_register_id'])->tenant_id,
            'cashier_membership_id' => TenantMembership::factory(),
            'opened_at' => now(),
            'opening_rupiah' => 200000,
            'status' => 'open',
        ];
    }
}
