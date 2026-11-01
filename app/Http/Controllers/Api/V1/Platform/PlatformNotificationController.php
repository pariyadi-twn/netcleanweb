<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\ReconcileNotificationRequest;
use App\Http\Resources\Api\V1\NotificationDeliveryResource;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Notifications\Actions\ReconcileNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;

class PlatformNotificationController extends Controller
{
    public function reconcile(
        ReconcileNotificationRequest $request,
        string $delivery,
        TenantScope $scope,
        ReconcileNotificationDelivery $reconcile,
    ): JsonResponse {
        abort_unless($request->user()?->is_platform_admin, 403);

        $result = $scope->identity($request->user(), function () use ($request, $delivery, $scope, $reconcile): NotificationDelivery {
            $model = NotificationDelivery::query()->where('public_id', $delivery)->firstOrFail();

            return $scope->forTenantId($model->tenant_id, $request->user()->id, fn (): NotificationDelivery => $reconcile->handle(
                $model,
                $request->string('status')->toString(),
                $request->input('provider_reference'),
                $request->input('reason'),
                $request->user(),
            ));
        });

        return response()->json([
            'data' => (new NotificationDeliveryResource($result->load('order')))->resolve($request),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
