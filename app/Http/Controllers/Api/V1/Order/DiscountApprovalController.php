<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\DecideDiscountApprovalRequest;
use App\Http\Resources\Api\V1\DiscountApprovalResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Order\Actions\DecideDiscountApproval;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DiscountApprovalController extends Controller
{
    public function __invoke(
        DecideDiscountApprovalRequest $request,
        string $quote,
        DecideDiscountApproval $decide,
    ): JsonResponse {
        Gate::authorize('create', Order::class);

        $context = app(TenantContext::class);

        if (! $context->can(PermissionCode::DiscountsOverride)) {
            abort(403);
        }

        $model = Quote::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $quote)
            ->firstOrFail();

        $approval = $decide->handle(
            $model,
            $request->input('decision') === 'approved',
            (string) $request->input('reason'),
            $context->membershipId,
        );

        return DiscountApprovalResource::make($approval->load(['quote', 'requestedBy', 'approvedBy']))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(200);
    }
}
