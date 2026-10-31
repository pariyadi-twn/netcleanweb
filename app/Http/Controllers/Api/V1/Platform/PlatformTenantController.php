<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformTenantResource;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Saas\Models\TenantSubscription;
use App\Modules\Saas\Models\TenantUsageCounter;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformTenantController extends Controller
{
    public function index(Request $request, TenantScope $scope): AnonymousResourceCollection
    {
        $this->authorizePlatform($request);

        $tenants = $scope->identity($request->user(), fn () => Tenant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get());

        foreach ($tenants as $tenant) {
            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', '!=', 'cancelled')
                ->with('plan')
                ->first();

            $tenant->subscription_summary = $subscription === null ? null : [
                'status' => $subscription->status->value,
                'plan_code' => $subscription->plan->code,
                'plan_revision' => $subscription->plan->revision,
                'current_period_end' => $subscription->current_period_end?->utc()->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        return PlatformTenantResource::collection($tenants);
    }

    public function show(Request $request, string $tenant, TenantScope $scope): JsonResponse
    {
        $this->authorizePlatform($request);

        $result = $scope->identity($request->user(), function () use ($request, $tenant, $scope): array {
            $model = Tenant::query()->where('public_id', $tenant)->firstOrFail();
            $subscription = TenantSubscription::query()
                ->where('tenant_id', $model->id)
                ->where('status', '!=', 'cancelled')
                ->with('plan')
                ->first();

            return $scope->forTenantId($model->id, $request->user()->id, function () use ($model, $subscription): array {
                $counters = TenantUsageCounter::query()
                    ->where('tenant_id', $model->id)
                    ->orderBy('metric_code')
                    ->get()
                    ->map(fn (TenantUsageCounter $counter): array => [
                        'metric_code' => $counter->metric_code,
                        'period' => $counter->period->toDateString(),
                        'used' => $counter->used,
                        'reserved' => $counter->reserved,
                    ])->values()->all();

                return [
                    'tenant' => [
                        'tenant_id' => $model->public_id,
                        'name' => $model->name,
                        'status' => $model->status->value,
                        'created_at' => $model->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                    ],
                    'subscription' => $subscription === null ? null : [
                        'status' => $subscription->status->value,
                        'plan_code' => $subscription->plan->code,
                        'plan_revision' => $subscription->plan->revision,
                        'current_period_start' => $subscription->current_period_start?->utc()->format('Y-m-d\TH:i:s\Z'),
                        'current_period_end' => $subscription->current_period_end?->utc()->format('Y-m-d\TH:i:s\Z'),
                    ],
                    'usage' => $counters,
                    'counts' => [
                        'branches' => Branch::query()->where('tenant_id', $model->id)->count(),
                        'memberships' => TenantMembership::query()->where('tenant_id', $model->id)->where('status', 'active')->count(),
                        'customers' => Customer::query()->where('tenant_id', $model->id)->count(),
                        'orders' => Order::query()->where('tenant_id', $model->id)->count(),
                        'payments' => Payment::query()->where('tenant_id', $model->id)->count(),
                    ],
                    'generated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                ];
            });
        });

        return response()->json([
            'data' => $result,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
    }
}
