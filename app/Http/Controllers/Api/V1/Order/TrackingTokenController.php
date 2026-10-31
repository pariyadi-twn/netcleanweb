<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Actions\IssueTrackingToken;
use App\Modules\Order\Actions\RevokeTrackingToken;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingTokenController extends Controller
{
    public function store(
        Request $request,
        string $order,
        IssueTrackingToken $issue,
    ): JsonResponse {
        $model = $this->findOrder($order);
        Gate::authorize('manageTracking', $model);

        $token = $issue->handle($model, app(TenantContext::class)->membershipId);

        return response()->json([
            'data' => [
                ...$token,
                'tracking_url' => url('/api/v1/tracking/'.$token['token']),
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function destroy(string $order, RevokeTrackingToken $revoke): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('manageTracking', $model);

        $revoked = $revoke->handle($model->tenant_id, $model->id);

        return response()->json([
            'data' => ['revoked' => $revoked],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function findOrder(string $publicId): Order
    {
        return Order::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
