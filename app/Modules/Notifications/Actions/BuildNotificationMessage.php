<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Order\Models\Order;

final class BuildNotificationMessage
{
    /**
     * @return array{subject: string, message: string, payload: array<string, mixed>}
     */
    public function handle(Order $order, NotificationChannel $channel, string $eventType): array
    {
        $branch = $order->pickupBranch ?? $order->salesBranch;
        $ready = $eventType === 'order.readiness_changed';
        $subject = $ready
            ? 'Pesanan '.$order->order_number.' siap diambil'
            : 'Pesanan '.$order->order_number.' diterima';
        $due = $order->due_at_current?->setTimezone($branch?->timezone ?: 'Asia/Jakarta')->format('d/m/Y H:i');
        $message = $ready
            ? 'Pesanan '.$order->order_number.' sudah siap diambil di '.($branch?->name ?? 'outlet').'.'
            : 'Pesanan '.$order->order_number.' sudah kami terima. Estimasi selesai: '.($due ?? '-').'.';

        if ($branch?->phone) {
            $message .= ' Hubungi '.$branch->phone.' bila perlu.';
        }

        return [
            'subject' => $subject,
            'message' => $message,
            'payload' => [
                'order_number' => $order->order_number,
                'status' => $order->operational_status->value,
                'due_at' => $order->due_at_current?->utc()->format('Y-m-d\TH:i:s\Z'),
                'branch_name' => $branch?->name,
                'channel' => $channel->value,
            ],
        ];
    }
}
