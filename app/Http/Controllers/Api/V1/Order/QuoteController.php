<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\StoreQuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Actions\CreateQuote;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class QuoteController extends Controller
{
    public function store(StoreQuoteRequest $request, CreateQuote $create): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id'))
            ->firstOrFail();

        abort_unless($context->canAccessBranch($branch), 403);

        $quote = $create->handle($context->tenantId, $request->validated());

        return QuoteResource::make($quote->load(['salesBranch', 'pickupBranch', 'customer']))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }
}
