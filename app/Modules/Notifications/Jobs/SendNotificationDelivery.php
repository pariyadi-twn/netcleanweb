<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Notifications\NotificationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $deliveryId, public readonly int $tenantId)
    {
        $this->onQueue((string) config('notifications.queue', 'notifications'));
    }

    public function handle(TenantScope $scope, NotificationSender $sender): void
    {
        $scope->forTenantId($this->tenantId, 0, function () use ($sender): void {
            $delivery = NotificationDelivery::query()->where('tenant_id', $this->tenantId)->lockForUpdate()->findOrFail($this->deliveryId);
            if ($delivery->status === NotificationStatus::Sent) {
                return;
            }

            $delivery->forceFill([
                'status' => NotificationStatus::Sending,
                'attempts' => $delivery->attempts + 1,
                'version' => $delivery->version + 1,
            ])->save();

            $result = $sender->send($delivery);
            $delivery->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerReference,
                'last_error_code' => $result->errorCode,
                'sent_at' => $result->status === NotificationStatus::Sent ? now() : null,
                'next_attempt_at' => $result->status === NotificationStatus::Failed ? now()->addSeconds(30) : null,
                'version' => $delivery->version + 1,
            ])->save();
        });
    }
}
