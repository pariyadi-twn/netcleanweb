<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    protected $model = TenantSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => SaasPlan::factory(),
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'entitlements_snapshot' => [
                'limits' => ['staff' => 5, 'branches' => 1],
                'features' => ['exports' => false],
            ],
            'grace_until' => null,
        ];
    }
}
