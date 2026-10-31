<?php

namespace Database\Factories;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Models\CreditApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditApproval>
 */
class CreditApprovalFactory extends Factory
{
    protected $model = CreditApproval::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): int => Invoice::query()->findOrFail($attributes['invoice_id'])->tenant_id,
            'invoice_id' => Invoice::factory(),
            'approved_by_membership_id' => TenantMembership::factory(),
            'limit_rupiah' => 41600,
            'expires_at' => now()->addDays(7),
            'reason' => 'Approved receivable',
        ];
    }
}
