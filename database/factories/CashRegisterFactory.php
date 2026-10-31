<?php

namespace Database\Factories;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'tenant_id' => fn (array $attributes): int => Branch::query()->findOrFail($attributes['branch_id'])->tenant_id,
            'code' => 'REG-'.fake()->unique()->numerify('####'),
            'is_active' => true,
        ];
    }
}
