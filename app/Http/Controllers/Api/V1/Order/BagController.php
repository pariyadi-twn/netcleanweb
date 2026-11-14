<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\ReceiveBagRequest;
use App\Http\Requests\Api\V1\Order\RepackBagRequest;
use App\Http\Resources\Api\V1\BagLabelResource;
use App\Http\Resources\Api\V1\BagResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Logistics\Actions\ReceiveBag;
use App\Modules\Order\Actions\RepackBag;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BagController extends Controller
{
    public function index(string $order): AnonymousResourceCollection
    {
        $model = $this->findOrder($order);
        Gate::authorize('view', $model);

        $bags = $model->bags()
            ->with(['contents.orderLine.service', 'order', 'currentLocation'])
            ->orderBy('id')
            ->get();

        return BagResource::collection($bags)->additional([
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function label(string $order, string $bag): BagLabelResource
    {
        $model = $this->findOrder($order);
        Gate::authorize('view', $model);

        $target = Bag::query()
            ->where('tenant_id', $model->tenant_id)
            ->where('order_id', $model->id)
            ->where('public_id', $bag)
            ->with(['contents.orderLine.service', 'order'])
            ->firstOrFail();

        return new BagLabelResource($target);
    }

    public function repack(RepackBagRequest $request, string $order, RepackBag $repack): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);

        $result = $repack->handle(
            $model,
            $request->validated(),
            app(TenantContext::class)->membershipId,
            $request->user(),
        );

        return response()->json([
            'data' => [
                'source' => BagResource::make($result['source']->load(['contents.orderLine.service', 'order', 'currentLocation']))->resolve(),
                'target' => BagResource::make($result['target']->load(['contents.orderLine.service', 'order', 'currentLocation']))->resolve(),
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function receive(ReceiveBagRequest $request, string $order, string $bag, ReceiveBag $receive): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);
        $result = $receive->handle($model, $bag, $request->validated(), $request->user());

        return response()->json([
            'data' => BagResource::make($result['bag']->load(['contents.orderLine.service', 'order', 'currentLocation']))->resolve(),
            'meta' => ['idempotent_replay' => $result['replay'], 'correlation_id' => CorrelationId::current()],
        ], $result['replay'] ? 200 : 201);
    }

    private function findOrder(string $publicId): Order
    {
        return Order::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
