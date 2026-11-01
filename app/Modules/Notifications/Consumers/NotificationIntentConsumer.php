<?php

namespace App\Modules\Notifications\Consumers;

use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Notifications\Actions\BuildNotificationMessage;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Jobs\SendNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Operations\Contracts\OutboxConsumer;
use App\Modules\Operations\Models\OutboxEvent;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

final class NotificationIntentConsumer implements OutboxConsumer
{
    public function __construct(private readonly BuildNotificationMessage $messages) {}

    public function handle(OutboxEvent $event): void
    {
        if (! in_array($event->event_type, ['order.accepted', 'order.readiness_changed'], true)) {
            return;
        }

        $payload = $event->payload;
        if (($event->event_type === 'order.readiness_changed') && (($payload['status'] ?? null) !== 'ready')) {
            return;
        }
        $orderPublicId = $payload['order_id'] ?? null;
        if (! is_string($orderPublicId)) {
            return;
        }

        $order = Order::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('public_id', $orderPublicId)
            ->with(['customer', 'salesBranch', 'pickupBranch'])
            ->first();
        if ($order === null || $order->customer_id === null) {
            return;
        }

        $customer = $order->customer;
        if (! $customer instanceof Customer) {
            return;
        }

        $channel = $this->channel($order->tenant_id, $customer, $order);
        if ($channel === null) {
            return;
        }

        $key = 'order:'.$order->id.':'.($event->event_type === 'order.accepted' ? 'accepted' : 'ready');
        $existing = NotificationDelivery::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('business_key', $key)
            ->where('channel', $channel->value)
            ->first();
        if ($existing !== null) {
            return;
        }

        $message = $this->messages->handle($order, $channel, $event->event_type);
        $recipient = $channel === NotificationChannel::Email ? $customer->email : $customer->normalized_phone;
        if ($recipient === null) {
            return;
        }

        $delivery = new NotificationDelivery([
            'event_id' => $event->event_id,
            'order_id' => $order->id,
            'channel' => $channel,
            'business_key' => $key,
            'recipient_ref' => $recipient,
            'subject' => $message['subject'],
            'message' => $message['message'],
            'payload' => $message['payload'],
            'status' => NotificationStatus::Queued,
        ]);
        $delivery->tenant_id = $event->tenant_id;
        $delivery->save();

        DB::afterCommit(fn () => SendNotificationDelivery::dispatch($delivery->id, $event->tenant_id));
    }

    private function channel(int $tenantId, Customer $customer, Order $order): ?NotificationChannel
    {
        $candidates = [];
        if ($customer->email !== null) {
            $candidates[] = [NotificationChannel::Email, ConsentChannel::Email];
        }
        if ($customer->normalized_phone !== null && config('notifications.whatsapp_endpoint') && config('notifications.whatsapp_token')) {
            $candidates[] = [NotificationChannel::Whatsapp, ConsentChannel::Whatsapp];
        }

        foreach ($candidates as [$channel, $consentChannel]) {
            $consent = CustomerConsent::query()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->where('purpose', ConsentPurpose::ServiceContact->value)
                ->where('channel', $consentChannel->value)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();
            if ($consent?->decision === ConsentDecision::Granted) {
                return $channel;
            }
        }

        return null;
    }
}
