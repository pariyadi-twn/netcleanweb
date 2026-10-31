<?php

namespace Database\Factories;

use App\Modules\Order\Models\DiscountApproval;
use App\Modules\Order\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountApproval>
 */
class DiscountApprovalFactory extends Factory
{
    protected $model = DiscountApproval::class;

    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'tenant_id' => fn (array $attributes): int => Quote::query()->findOrFail($attributes['quote_id'])->tenant_id,
            'percent' => 10,
            'requested_by_membership_id' => 1,
            'approved_by_membership_id' => null,
            'status' => 'pending',
            'reason' => null,
            'decision_reason' => null,
            'decided_at' => null,
        ];
    }
}
