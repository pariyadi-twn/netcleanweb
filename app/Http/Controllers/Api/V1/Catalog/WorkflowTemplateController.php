<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ListCatalogRequest;
use App\Http\Requests\Api\V1\Catalog\StoreWorkflowRevisionRequest;
use App\Http\Resources\Api\V1\WorkflowTemplateResource;
use App\Modules\Catalog\Actions\CreateWorkflowRevision;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkflowTemplateController extends Controller
{
    public function index(ListCatalogRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WorkflowTemplate::class);

        $query = WorkflowTemplate::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->with('steps')
            ->orderBy('code')
            ->orderByDesc('revision')
            ->orderBy('id');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $paginator = $query->cursorPaginate((int) $request->integer('per_page', 25));

        return WorkflowTemplateResource::collection($paginator->items())->additional([
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
    public function store(StoreWorkflowRevisionRequest $request, CreateWorkflowRevision $create): JsonResponse
    {
        Gate::authorize('create', WorkflowTemplate::class);

        $workflow = $create->handle(app(TenantContext::class)->tenantId, $request->validated());

        return WorkflowTemplateResource::make($workflow)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $workflowTemplate): WorkflowTemplateResource
    {
        $workflow = WorkflowTemplate::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $workflowTemplate)
            ->firstOrFail();

        Gate::authorize('view', $workflow);

        return new WorkflowTemplateResource($workflow->load('steps'));
    }
}
