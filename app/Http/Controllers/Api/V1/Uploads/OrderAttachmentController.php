<?php

namespace App\Http\Controllers\Api\V1\Uploads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Uploads\AttachOrderRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Order;
use App\Modules\Uploads\Actions\AttachToOrder;
use App\Modules\Uploads\Models\Attachment;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderAttachmentController extends Controller
{
    public function index(string $order): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('view', $model);

        $ids = DB::table('order_attachments')
            ->where('tenant_id', $model->tenant_id)
            ->where('order_id', $model->id)
            ->pluck('attachment_id');

        $attachments = Attachment::query()
            ->where('tenant_id', $model->tenant_id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => AttachmentResource::collection($attachments)->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(AttachOrderRequest $request, string $order, AttachToOrder $attach): JsonResponse
    {
        $model = $this->findOrder($order);
        Gate::authorize('revise', $model);

        $attachment = Attachment::query()
            ->where('tenant_id', $model->tenant_id)
            ->where('public_id', $request->string('attachment_id'))
            ->firstOrFail();

        $attach->handle($model, $attachment, (string) $request->input('purpose'));

        return AttachmentResource::make($attachment)
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
