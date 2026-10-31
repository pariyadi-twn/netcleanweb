<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ListCatalogRequest;
use App\Http\Requests\Api\V1\Catalog\StoreServiceRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Modules\Catalog\Actions\CreateService;
use App\Modules\Catalog\Actions\UpdateService;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function index(ListCatalogRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Service::class);

        $query = Service::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->with('workflowTemplate.steps')
            ->orderBy('code')
            ->orderBy('id');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $paginator = $query->cursorPaginate((int) $request->integer('per_page', 25));

        return ServiceResource::collection($paginator->items())->additional([
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'watermark' => null,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request, CreateService $create): JsonResponse
    {
        Gate::authorize('create', Service::class);

        $service = $create->handle(app(TenantContext::class)->tenantId, $request->validated());

        return ServiceResource::make($service)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $service): ServiceResource
    {
        $model = $this->findService($service);

        Gate::authorize('view', $model);

        return new ServiceResource($model->load('workflowTemplate.steps'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRequest $request, string $service, UpdateService $update): ServiceResource
    {
        $model = $this->findService($service);

        Gate::authorize('update', $model);

        return new ServiceResource($update->handle($model, $request->validated()));
    }

    private function findService(string $publicId): Service
    {
        return Service::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
