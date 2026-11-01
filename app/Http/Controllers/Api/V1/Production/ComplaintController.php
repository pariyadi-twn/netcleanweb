<?php

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Production\CreateComplaintRequest;
use App\Http\Requests\Api\V1\Production\ResolveComplaintRequest;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Http\Resources\Api\V1\ProductionTaskResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Actions\CreateComplaint;
use App\Modules\Production\Actions\PlanRewash;
use App\Modules\Production\Actions\ResolveComplaint;
use App\Modules\Production\Models\Complaint;
use App\Modules\Production\Models\ProductionTask;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function index(Request $request, string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $order = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($order))
            ->firstOrFail();

        $complaints = Complaint::query()
            ->where('tenant_id', $context->tenantId)
            ->where('order_id', $order->id)
            ->with(['order', 'orderLine', 'attachments', 'approvedAdjustment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($complaints as $complaint) {
            Gate::authorize('view', $complaint);
        }

        return response()->json([
            'data' => ComplaintResource::collection($complaints)->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(CreateComplaintRequest $request, string $order, CreateComplaint $create): JsonResponse
    {
        $context = app(TenantContext::class);
        $order = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($order))
            ->firstOrFail();
        Gate::authorize('create', [Complaint::class, $order]);

        $complaint = $create->handle($order, $request->validated(), $context->membershipId, $request->user());

        return ComplaintResource::make($complaint)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function rewash(Request $request, string $complaint, PlanRewash $plan): JsonResponse
    {
        $model = $this->find($complaint);
        Gate::authorize('manage', $model);
        $context = app(TenantContext::class);
        $tasks = $plan->handle($model, $context->membershipId, $request->user());

        $tasks = (new ProductionTask)->newCollection($tasks)->load(['content.orderLine', 'content.order', 'content.bag', 'location']);

        return response()->json([
            'data' => ProductionTaskResource::collection($tasks)->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function resolve(ResolveComplaintRequest $request, string $complaint, ResolveComplaint $resolve): JsonResponse
    {
        $model = $this->find($complaint);
        Gate::authorize('manage', $model);
        $context = app(TenantContext::class);
        $model = $resolve->handle(
            $model,
            $request->string('status')->toString(),
            $request->string('resolution')->toString(),
            $request->input('approved_adjustment_id'),
            $context->membershipId,
            $request->user(),
        );

        return response()->json([
            'data' => (new ComplaintResource($model))->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function find(string $publicId): Complaint
    {
        return Complaint::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', Str::lower($publicId))
            ->firstOrFail();
    }
}
