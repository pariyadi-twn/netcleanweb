<?php

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Production\ListProductionTasksRequest;
use App\Http\Requests\Api\V1\Production\PlanProductionTasksRequest;
use App\Http\Requests\Api\V1\Production\TransitionProductionTaskRequest;
use App\Http\Resources\Api\V1\ProductionTaskResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Location;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Actions\PlanProductionTasks;
use App\Modules\Production\Actions\TransitionProductionTask;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductionTaskController extends Controller
{
    public function index(ListProductionTasksRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionTask::class);
        $context = app(TenantContext::class);
        $location = Location::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('location_id'))
            ->firstOrFail();
        abort_unless($context->canAccessLocation($location), 403);

        $query = ProductionTask::query()
            ->where('tenant_id', $context->tenantId)
            ->where('production_location_id', $location->id)
            ->with(['content.orderLine', 'content.order', 'content.bag', 'location'])
            ->orderBy('due_at')
            ->orderBy('sequence')
            ->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $paginator = $query->cursorPaginate(
            (int) $request->integer('per_page', 25),
            ['*'],
            'cursor',
            $request->input('cursor'),
        );

        return response()->json([
            'data' => ProductionTaskResource::collection($paginator->items())->resolve($request),
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'location_id' => $location->public_id,
            ],
        ]);
    }

    public function plan(
        PlanProductionTasksRequest $request,
        string $order,
        PlanProductionTasks $plan,
    ): JsonResponse {
        $context = app(TenantContext::class);
        $order = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $order)
            ->firstOrFail();
        $location = Location::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('location_id'))
            ->firstOrFail();
        Gate::authorize('plan', [ProductionTask::class, $location]);

        $tasks = $plan->handle($order, $location, $context->membershipId, $request->user());

        return response()->json([
            'data' => ProductionTaskResource::collection((new ProductionTask)->newCollection($tasks)->load(['content.orderLine', 'content.order', 'content.bag', 'location']))->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function transition(
        TransitionProductionTaskRequest $request,
        string $task,
        TransitionProductionTask $transition,
    ): JsonResponse {
        $context = app(TenantContext::class);
        $model = ProductionTask::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($task))
            ->firstOrFail();
        Gate::authorize('transition', $model);

        $model = $transition->handle(
            $model,
            ProductionTaskStatus::from($request->string('to_status')->toString()),
            (int) $request->integer('expected_version'),
            $context->membershipId,
            $request->input('reason'),
            $request->user(),
        );
        $model->load(['content.orderLine', 'content.order', 'content.bag', 'location']);

        return response()->json([
            'data' => (new ProductionTaskResource($model))->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
