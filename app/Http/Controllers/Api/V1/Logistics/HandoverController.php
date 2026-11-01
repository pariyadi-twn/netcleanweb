<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Logistics\CreateHandoverRequest;
use App\Http\Resources\Api\V1\HandoverResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Logistics\Actions\CreateHandover;
use App\Modules\Logistics\Models\Handover;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class HandoverController extends Controller
{
    public function store(CreateHandoverRequest $request, string $order, CreateHandover $create): JsonResponse
    {
        $context = app(TenantContext::class);
        $order = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($order))
            ->firstOrFail();
        Gate::authorize('create', [Handover::class, $order]);

        $result = $create->handle($order, $request->validated(), $context->membershipId, $request->user());
        $handover = $result['handover']->load(['order', 'location', 'creditApproval', 'lines.content']);

        return response()->json([
            'data' => [
                'handover' => (new HandoverResource($handover))->resolve($request),
                'order' => [
                    'order_id' => $result['order']->public_id,
                    'operational_status' => $result['order']->operational_status->value,
                ],
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }
}
