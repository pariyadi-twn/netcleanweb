<?php

namespace App\Modules\Uploads\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachToOrder
{
    public function handle(Order $order, Attachment $attachment, string $purpose): void
    {
        if ($attachment->tenant_id !== $order->tenant_id || $attachment->state !== AttachmentState::Ready) {
            throw ValidationException::withMessages([
                'attachment_id' => ['The attachment must be ready and belong to this tenant.'],
            ]);
        }

        $exists = DB::table('order_attachments')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('attachment_id', $attachment->id)
            ->exists();

        if (! $exists) {
            DB::table('order_attachments')->insert([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'attachment_id' => $attachment->id,
                'purpose' => $purpose,
                'created_at' => now(),
            ]);
        }
    }
}
