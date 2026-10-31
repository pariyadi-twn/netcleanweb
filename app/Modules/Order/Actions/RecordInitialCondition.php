<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderConditionEvidence;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordInitialCondition
{
    /**
     * @param  list<string>  $attachmentPublicIds
     */
    public function handle(
        Order $order,
        ?string $notes,
        array $attachmentPublicIds,
        int $membershipId,
    ): OrderConditionEvidence {
        return DB::transaction(function () use ($order, $notes, $attachmentPublicIds, $membershipId): OrderConditionEvidence {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attachments = Attachment::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('public_id', $attachmentPublicIds)
                ->get();

            if ($attachments->count() !== count(array_unique($attachmentPublicIds))) {
                throw ValidationException::withMessages([
                    'attachment_ids' => ['Every attachment must belong to this tenant.'],
                ]);
            }

            foreach ($attachments as $attachment) {
                $linkedToOrder = DB::table('order_attachments')
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('order_id', $locked->id)
                    ->where('attachment_id', $attachment->id)
                    ->exists();

                if ($attachment->state !== AttachmentState::Ready) {
                    throw ValidationException::withMessages([
                        'attachment_ids' => ['Only ready attachments can be recorded as initial condition.'],
                    ]);
                }

                if ($attachment->uploaded_by_membership_id !== $membershipId && ! $linkedToOrder) {
                    throw ValidationException::withMessages([
                        'attachment_ids' => ['Only the uploader can attach an unlinked attachment.'],
                    ]);
                }
            }

            $evidence = OrderConditionEvidence::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($evidence === null) {
                $evidence = new OrderConditionEvidence([
                    'order_id' => $locked->id,
                    'notes' => $notes,
                    'recorded_by_membership_id' => $membershipId,
                    'recorded_at' => now(),
                ]);
                $evidence->tenant_id = $locked->tenant_id;
                $evidence->save();
            } else {
                $evidence->forceFill([
                    'notes' => $notes,
                    'recorded_by_membership_id' => $membershipId,
                    'recorded_at' => now(),
                    'version' => $evidence->version + 1,
                ])->save();
            }

            foreach ($attachments as $attachment) {
                $exists = DB::table('order_attachments')
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('order_id', $locked->id)
                    ->where('attachment_id', $attachment->id)
                    ->exists();

                if (! $exists) {
                    DB::table('order_attachments')->insert([
                        'tenant_id' => $locked->tenant_id,
                        'order_id' => $locked->id,
                        'attachment_id' => $attachment->id,
                        'purpose' => 'initial_condition',
                        'created_at' => now(),
                    ]);
                }
            }

            return $evidence->refresh();
        });
    }
}
