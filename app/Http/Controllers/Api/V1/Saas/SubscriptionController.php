<?php

namespace App\Http\Controllers\Api\V1\Saas;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Saas\SubscriptionEntitlements;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __invoke(Request $request, SubscriptionEntitlements $entitlements): JsonResponse
    {
        $context = app(TenantContext::class);
        $subscription = $entitlements->activeFor($context->tenantId);
        $subscription->load('plan');

        return response()->json([
            'data' => [
                'subscription_id' => $subscription->public_id,
                'status' => $subscription->status->value,
                'plan_code' => $subscription->plan->code,
                'plan_revision' => $subscription->plan->revision,
                'current_period_start' => $subscription->current_period_start->utc()->format('Y-m-d\TH:i:s\Z'),
                'current_period_end' => $subscription->current_period_end->utc()->format('Y-m-d\TH:i:s\Z'),
                'limits' => $subscription->entitlements_snapshot['limits'] ?? [],
                'features' => $subscription->entitlements_snapshot['features'] ?? [],
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
