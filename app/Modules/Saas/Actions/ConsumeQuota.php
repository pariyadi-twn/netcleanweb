<?php

namespace App\Modules\Saas\Actions;

use App\Exceptions\ApiException;
use App\Modules\Saas\SubscriptionEntitlements;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConsumeQuota
{
    public function __construct(private readonly SubscriptionEntitlements $entitlements) {}

    public function handle(int $tenantId, string $metric, int $amount = 1): int
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Quota amount must be positive.');
        }

        $subscription = $this->entitlements->activeFor($tenantId);
        $limit = (int) data_get($subscription->entitlements_snapshot, 'limits.'.$metric, 0);
        $period = $subscription->current_period_start->toDateString();

        DB::statement(
            'INSERT INTO tenant_usage_counters (tenant_id, metric_code, period, used, reserved, created_at, updated_at)
             VALUES (?, ?, ?, 0, 0, NOW(), NOW())
             ON CONFLICT (tenant_id, metric_code, period) DO NOTHING',
            [$tenantId, $metric, $period],
        );

        $updated = DB::selectOne(
            'UPDATE tenant_usage_counters
             SET used = used + ?, updated_at = NOW()
             WHERE tenant_id = ? AND metric_code = ? AND period = ? AND used + ? <= ?
             RETURNING used',
            [$amount, $tenantId, $metric, $period, $amount, $limit],
        );

        if ($updated === null) {
            throw new ApiException('ENTITLEMENT_LIMIT_REACHED', 'The plan quota has been reached.', 409);
        }

        return (int) $updated->used;
    }

    public function release(int $tenantId, string $metric, int $amount = 1): void
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Quota amount must be positive.');
        }

        $subscription = $this->entitlements->activeFor($tenantId);
        $period = $subscription->current_period_start->toDateString();

        DB::statement(
            'UPDATE tenant_usage_counters
             SET used = GREATEST(used - ?, 0), updated_at = NOW()
             WHERE tenant_id = ? AND metric_code = ? AND period = ?',
            [$amount, $tenantId, $metric, $period],
        );
    }

    public function withQuota(int $tenantId, string $metric, int $amount, Closure $callback): mixed
    {
        return DB::transaction(function () use ($tenantId, $metric, $amount, $callback): mixed {
            $this->handle($tenantId, $metric, $amount);

            return $callback();
        });
    }
}
