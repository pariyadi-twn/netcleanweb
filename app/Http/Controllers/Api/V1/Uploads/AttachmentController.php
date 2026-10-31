<?php

namespace App\Http\Controllers\Api\V1\Uploads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Uploads\InitAttachmentRequest;
use App\Http\Requests\Api\V1\Uploads\TransferAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Order;
use App\Modules\Uploads\Actions\AttachmentUrl;
use App\Modules\Uploads\Actions\FinalizeAttachment;
use App\Modules\Uploads\Actions\InitAttachment;
use App\Modules\Uploads\Actions\TransferAttachment;
use App\Modules\Uploads\Models\Attachment;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachmentController extends Controller
{
    public function store(InitAttachmentRequest $request, InitAttachment $init): JsonResponse
    {
        Gate::authorize('create', Order::class);
        $context = app(TenantContext::class);
        $attachment = $init->handle($context->tenantId, $context->membershipId, $request->validated());

        return AttachmentResource::make($attachment)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function transfer(TransferAttachmentRequest $request, string $attachment, TransferAttachment $transfer): AttachmentResource
    {
        Gate::authorize('create', Order::class);
        $model = $this->findAttachment($attachment);
        $transfer->handle($model, $request->file('file'), app(TenantContext::class)->membershipId);

        return new AttachmentResource($model->refresh());
    }

    public function finalize(string $attachment, FinalizeAttachment $finalize): AttachmentResource
    {
        Gate::authorize('create', Order::class);
        $model = $this->findAttachment($attachment);

        return new AttachmentResource($finalize->handle($model, app(TenantContext::class)->membershipId));
    }

    public function url(string $attachment, AttachmentUrl $url): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findAttachment($attachment);

        if ($model->uploaded_by_membership_id !== $context->membershipId) {
            $orderIds = DB::table('order_attachments')
                ->where('tenant_id', $model->tenant_id)
                ->where('attachment_id', $model->id)
                ->pluck('order_id');

            if ($orderIds->isEmpty()) {
                abort(403);
            }

            $linked = Order::query()->where('tenant_id', $model->tenant_id)->whereIn('id', $orderIds)->get();
            foreach ($linked as $order) {
                Gate::authorize('view', $order);
            }
        }

        return response()->json([
            'data' => [
                'url' => $url->handle($model),
                'expires_at' => now()->addMinutes((int) config('uploads.signed_url_minutes'))->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function findAttachment(string $publicId): Attachment
    {
        return Attachment::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
