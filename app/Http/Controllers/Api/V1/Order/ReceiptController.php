<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\BuildReceiptRequest;
use App\Http\Requests\Api\V1\Order\RecordReceiptPrintRequest;
use App\Http\Resources\Api\V1\ReceiptPrintAttemptResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Actions\BuildReceipt;
use App\Modules\Order\Actions\RecordReceiptPrint;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    public function show(BuildReceiptRequest $request, string $order, BuildReceipt $build): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('view', $model);

        return response()->json([
            'data' => $build->handle($model, $request->input('tracking_token')),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function print(
        RecordReceiptPrintRequest $request,
        string $order,
        RecordReceiptPrint $record,
    ): JsonResponse {
        $model = $this->findOrder($order);
        Gate::authorize('printReceipt', $model);

        $attempt = $record->handle(
            $model,
            (string) $request->input('outcome'),
            $request->input('error_code'),
            $request->input('tracking_token'),
            app(TenantContext::class)->membershipId,
            $request->user(),
        );

        return ReceiptPrintAttemptResource::make($attempt->load('order'))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    private function findOrder(string $publicId): Order
    {
        return Order::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
