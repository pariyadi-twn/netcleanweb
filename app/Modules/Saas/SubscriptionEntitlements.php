<?php

namespace App\Modules\Saas;

use App\Exceptions\ApiException;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Modules\Saas\Models\TenantSubscription;

final class SubscriptionEntitlements
{
    public function activeFor(int $tenantId): TenantSubscription
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                SubscriptionStatus::Trial->value,
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Grace->value,
            ])
            ->first();

        if ($subscription === null) {
            throw new ApiException('SUBSCRIPTION_INACTIVE', 'The tenant has no active subscription.', 403);
        }

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $tenantId): array
    {
        return $this->activeFor($tenantId)->entitlements_snapshot;
    }

    public function limit(int $tenantId, string $metric): int
    {
        return (int) data_get($this->snapshot($tenantId), 'limits.'.$metric, 0);
    }

    public function allows(int $tenantId, string $feature): bool
    {
        return (bool) data_get($this->snapshot($tenantId), 'features.'.$feature, false);
    }

    public function requireFeature(int $tenantId, string $feature): void
    {
        if (! $this->allows($tenantId, $feature)) {
            throw new ApiException('FEATURE_NOT_AVAILABLE', 'The requested feature is not available on this plan.', 403);
        }
    }
}
