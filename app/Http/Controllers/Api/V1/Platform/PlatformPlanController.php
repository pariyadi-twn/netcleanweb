<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\AssignSubscriptionRequest;
use App\Http\Requests\Api\V1\Platform\StorePlanRequest;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\TenantSubscription;
use App\Support\Http\CorrelationId;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        return response()->json([
            'data' => SaasPlan::query()->orderBy('code')->orderBy('revision')->get()->map(fn (SaasPlan $plan): array => $this->planData($plan))->values()->all(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $plan = SaasPlan::query()->create($request->validated());

        return response()->json([
            'data' => $this->planData($plan),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function assign(
        AssignSubscriptionRequest $request,
        string $tenant,
        TenantScope $scope,
        StartSubscription $start,
    ): JsonResponse {
        $this->authorizePlatform($request);

        $result = $scope->identity($request->user(), function () use ($request, $tenant, $start): array {
            $model = Tenant::query()->where('public_id', $tenant)->firstOrFail();
            $plan = SaasPlan::query()
                ->where('code', $request->input('plan_code'))
                ->where('revision', (int) $request->input('plan_revision'))
                ->firstOrFail();

            if (TenantSubscription::query()->where('tenant_id', $model->id)->where('status', '!=', 'cancelled')->exists()) {
                abort(409, 'The tenant already has an effective subscription.');
            }

            $subscription = $start->handle(
                $model,
                $plan,
                SubscriptionStatus::from($request->input('status', 'active')),
                CarbonImmutable::parse($request->input('period_start')),
                CarbonImmutable::parse($request->input('period_end')),
            );

            return [
                'tenant_id' => $model->public_id,
                'plan_code' => $plan->code,
                'plan_revision' => $plan->revision,
                'status' => $subscription->status->value,
                'period_start' => $subscription->current_period_start?->utc()->format('Y-m-d\TH:i:s\Z'),
                'period_end' => $subscription->current_period_end?->utc()->format('Y-m-d\TH:i:s\Z'),
            ];
        });

        return response()->json([
            'data' => $result,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function planData(SaasPlan $plan): array
    {
        return [
            'code' => $plan->code,
            'revision' => $plan->revision,
            'name' => $plan->name,
            'base_price_rupiah' => $plan->base_price_rupiah,
            'features' => $plan->features,
            'is_active' => $plan->is_active,
        ];
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
    }
}
