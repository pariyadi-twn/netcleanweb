<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\RecordInitialConditionRequest;
use App\Http\Resources\Api\V1\OrderConditionResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Actions\RecordInitialCondition;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderConditionEvidence;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderConditionController extends Controller
{
    public function show(string $order): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('view', $model);

        $evidence = OrderConditionEvidence::query()
            ->where('tenant_id', $model->tenant_id)
            ->where('order_id', $model->id)
            ->with(['order', 'recordedBy'])
            ->first();

        return response()->json([
            'data' => $evidence === null
                ? null
                : OrderConditionResource::make($evidence)->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(
        RecordInitialConditionRequest $request,
        string $order,
        RecordInitialCondition $record,
    ): JsonResponse {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);

        $evidence = $record->handle(
            $model,
            $request->input('notes'),
            $request->array('attachment_ids'),
            app(TenantContext::class)->membershipId,
        );

        return OrderConditionResource::make($evidence->load(['order', 'recordedBy']))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    private function findOrder(string $publicId): Order
    {
        return Order::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
