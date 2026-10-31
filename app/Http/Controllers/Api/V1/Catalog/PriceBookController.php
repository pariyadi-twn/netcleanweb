<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ListPriceBookRequest;
use App\Http\Requests\Api\V1\Catalog\StorePriceBookRequest;
use App\Http\Resources\Api\V1\PriceBookResource;
use App\Modules\Catalog\Actions\CreatePriceBook;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PriceBookController extends Controller
{
    public function index(ListPriceBookRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PriceBook::class);

        $context = app(TenantContext::class);
        $query = PriceBook::query()
            ->where('tenant_id', $context->tenantId)
            ->whereNull('customer_id')
            ->with(['branch', 'entries.service'])
            ->orderBy('name')
            ->orderBy('id');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('branch_id')) {
            $branchId = Branch::query()
                ->where('tenant_id', $context->tenantId)
                ->where('public_id', $request->string('branch_id'))
                ->value('id');

            $query->where('branch_id', $branchId);
        }

        $paginator = $query->cursorPaginate((int) $request->integer('per_page', 25));

        return PriceBookResource::collection($paginator->items())->additional([
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'watermark' => null,
            ],
        ]);
    }

    public function store(StorePriceBookRequest $request, CreatePriceBook $create): JsonResponse
    {
        Gate::authorize('create', PriceBook::class);

        $book = $create->handle(app(TenantContext::class)->tenantId, $request->validated());

        return PriceBookResource::make($book)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $priceBook): PriceBookResource
    {
        $model = $this->findPriceBook($priceBook);

        Gate::authorize('view', $model);

        return new PriceBookResource($model->load(['branch', 'entries.service']));
    }

    private function findPriceBook(string $publicId): PriceBook
    {
        return PriceBook::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->whereNull('customer_id')
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
