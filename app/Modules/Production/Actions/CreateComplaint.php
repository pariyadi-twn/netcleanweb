<?php

namespace App\Modules\Production\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\Complaint;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateComplaint
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  array{kind: string, order_line_id?: string|null, description: string, attachment_ids?: array<int, string>}  $attributes
     */
    public function handle(Order $order, array $attributes, int $membershipId, ?User $actor = null): Complaint
    {
        return DB::transaction(function () use ($order, $attributes, $membershipId, $actor): Complaint {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lineId = null;
            if (! empty($attributes['order_line_id'])) {
                $line = $locked->lines()->where('public_id', $attributes['order_line_id'])->first();
                if ($line === null) {
                    throw ValidationException::withMessages(['order_line_id' => ['The order line does not belong to this order.']]);
                }
                $lineId = $line->id;
            }

            $attachmentIds = array_values(array_unique($attributes['attachment_ids'] ?? []));
            $attachments = Attachment::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('public_id', $attachmentIds)
                ->get();
            if ($attachments->count() !== count($attachmentIds)) {
                throw ValidationException::withMessages(['attachment_ids' => ['Every attachment must belong to this tenant.']]);
            }
            foreach ($attachments as $attachment) {
                $linkedToOrder = DB::table('order_attachments')
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('order_id', $locked->id)
                    ->where('attachment_id', $attachment->id)
                    ->exists();
                if ($attachment->state !== AttachmentState::Ready || ($attachment->uploaded_by_membership_id !== $membershipId && ! $linkedToOrder)) {
                    throw ValidationException::withMessages(['attachment_ids' => ['Only ready attachments owned by the uploader or linked to the order can be used.']]);
                }
            }

            $complaint = new Complaint([
                'order_id' => $locked->id,
                'order_line_id' => $lineId,
                'kind' => $attributes['kind'],
                'status' => 'open',
                'description' => $attributes['description'],
                'opened_by_membership_id' => $membershipId,
            ]);
            $complaint->tenant_id = $locked->tenant_id;
            $complaint->save();

            foreach ($attachments as $attachment) {
                DB::table('complaint_attachments')->insert([
                    'tenant_id' => $locked->tenant_id,
                    'complaint_id' => $complaint->id,
                    'attachment_id' => $attachment->id,
                    'created_at' => now(),
                ]);
            }

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ComplaintCreated,
                subjectType: AuditRecorder::SUBJECT_COMPLAINT,
                subjectPublicId: $complaint->public_id,
                changes: ['order_id' => $locked->public_id, 'kind' => $complaint->kind, 'line_id' => $attributes['order_line_id'] ?? null],
                reason: $complaint->description,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'complaint.created',
                aggregatePublicId: $complaint->public_id,
                payload: ['complaint_id' => $complaint->public_id, 'order_id' => $locked->public_id, 'kind' => $complaint->kind],
            );

            return $complaint->refresh()->load(['order', 'orderLine', 'attachments']);
        });
    }
}
