<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\DecideOrderRevisionRequest;
use App\Http\Requests\Api\V1\Order\StoreOrderRevisionRequest;
use App\Http\Resources\Api\V1\OrderRevisionResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Order\Actions\CreateOrderRevision;
use App\Modules\Order\Actions\DecideOrderRevision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderRevision;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderRevisionController extends Controller
{
    public function store(
        StoreOrderRevisionRequest $request,
        string $order,
        CreateOrderRevision $create,
    ): JsonResponse {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);

        $revision = $create->handle(
            $model,
            $request->validated(),
            app(TenantContext::class)->membershipId,
            $request->user(),
        );

        return $this->response($revision, 201);
    }

    public function decide(
        DecideOrderRevisionRequest $request,
        string $order,
        string $revision,
        DecideOrderRevision $decide,
    ): JsonResponse {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);

        $context = app(TenantContext::class);
        if (! $context->can(PermissionCode::DiscountsOverride)) {
            abort(403);
        }

        $target = OrderRevision::query()
            ->where('tenant_id', $context->tenantId)
            ->where('order_id', $model->id)
            ->where('public_id', $revision)
            ->firstOrFail();

        $decided = $decide->handle(
            $target,
            $request->input('decision') === 'approved',
            (string) $request->input('reason'),
            $context->membershipId,
            $request->user(),
        );

        return $this->response($decided, 200);
    }

    private function response(OrderRevision $revision, int $status): JsonResponse
    {
        return OrderRevisionResource::make($revision->load(['order', 'requestedBy', 'approvedBy', 'adjustment']))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode($status);
    }

    private function findOrder(string $publicId): Order
    {
        return Order::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
