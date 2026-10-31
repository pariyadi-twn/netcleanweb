<?php

namespace App\Modules\Saas\Actions;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\TenantSubscription;
use DateTimeInterface;
use InvalidArgumentException;

final class StartSubscription
{
    public function handle(
        Tenant $tenant,
        SaasPlan $plan,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?DateTimeInterface $periodStart = null,
        ?DateTimeInterface $periodEnd = null,
    ): TenantSubscription {
        $features = $plan->features;

        if (! is_array($features)) {
            throw new InvalidArgumentException('Plan features must be an array.');
        }

        $limits = $features['limits'] ?? [];
        $featureFlags = $features['features'] ?? [];

        if (! is_array($limits) || ! is_array($featureFlags)) {
            throw new InvalidArgumentException('Plan limits and features must be arrays.');
        }

        foreach ($limits as $metric => $limit) {
            if (! is_string($metric) || ! is_int($limit) || $limit < 0) {
                throw new InvalidArgumentException('Plan limits must be non-negative integers.');
            }
        }

        foreach ($featureFlags as $feature => $enabled) {
            if (! is_string($feature) || ! is_bool($enabled)) {
                throw new InvalidArgumentException('Plan features must be boolean flags.');
            }
        }

        $subscription = new TenantSubscription([
            'plan_id' => $plan->id,
            'status' => $status,
            'current_period_start' => $periodStart ?? now()->startOfMonth(),
            'current_period_end' => $periodEnd ?? now()->endOfMonth(),
            'entitlements_snapshot' => [
                'limits' => $limits,
                'features' => $featureFlags,
            ],
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        return $subscription;
    }
}
