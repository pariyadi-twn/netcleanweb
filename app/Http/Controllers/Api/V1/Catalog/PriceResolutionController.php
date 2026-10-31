<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ResolvePriceRequest;
use App\Modules\Catalog\Actions\ResolvePrice;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PriceResolutionController extends Controller
{
    public function __invoke(ResolvePriceRequest $request, ResolvePrice $resolve): JsonResponse
    {
        Gate::authorize('viewAny', PriceBook::class);

        $resolved = $resolve->handle(
            app(TenantContext::class)->tenantId,
            (string) $request->string('branch_id'),
            (string) $request->string('service_id'),
            PriceTier::from((string) $request->input('tier', PriceTier::Regular->value)),
            $request->input('at'),
        );

        return response()->json([
            'data' => $resolved,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
