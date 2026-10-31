<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operations\PreviewDeadlineRequest;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Actions\CalculateDeadline;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeadlinePreviewController extends Controller
{
    public function __invoke(PreviewDeadlineRequest $request, CalculateDeadline $calculate): JsonResponse
    {
        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id'))
            ->firstOrFail();
        $service = Service::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('service_id'))
            ->firstOrFail();

        Gate::authorize('view', $branch);

        $deadline = $calculate->handle(
            $branch,
            $service,
            PriceTier::from((string) $request->input('tier', PriceTier::Regular->value)),
            $request->input('accepted_at'),
        );

        return response()->json([
            'data' => $deadline,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
