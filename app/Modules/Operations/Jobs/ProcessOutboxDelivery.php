<?php

namespace App\Modules\Operations\Jobs;

use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Operations\Models\ConsumerReceipt;
use App\Modules\Operations\Models\EventDelivery;
use App\Modules\Operations\Models\OutboxEvent;
use App\Modules\Operations\Outbox\OutboxConsumerRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessOutboxDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $deliveryId,
        public int $tenantId,
    ) {}

    public function handle(OutboxConsumerRegistry $registry, TenantScope $scope): void
    {
        $scope->forTenantId($this->tenantId, 0, function () use ($registry): void {
            $delivery = EventDelivery::query()->findOrFail($this->deliveryId);
            $event = OutboxEvent::query()
                ->where('tenant_id', $this->tenantId)
                ->where('event_id', $delivery->event_id)
                ->firstOrFail();

            if ($this->receiptExists($delivery)) {
                $delivery->forceFill(['lease_until' => null, 'last_error_code' => null])->save();

                return;
            }

            $consumer = $registry->for($delivery->consumer_name);

            DB::transaction(function () use ($delivery, $event, $consumer): void {
                $locked = EventDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

                if ($this->receiptExists($locked)) {
                    return;
                }

                $consumer->handle($event);

                ConsumerReceipt::query()->create([
                    'tenant_id' => $locked->tenant_id,
                    'event_id' => $locked->event_id,
                    'consumer_name' => $locked->consumer_name,
                    'applied_at' => now(),
                ]);

                $locked->forceFill(['lease_until' => null, 'last_error_code' => null])->save();
            });
        });
    }

    public function failed(Throwable $exception): void
    {
        app(TenantScope::class)->forTenantId($this->tenantId, 0, function (): void {
            EventDelivery::query()->whereKey($this->deliveryId)->update([
                'lease_until' => null,
                'last_error_code' => 'CONSUMER_FAILED',
                'next_attempt_at' => now()->addSeconds((int) config('outbox.retry_seconds', 30)),
            ]);
        });
    }

    private function receiptExists(EventDelivery $delivery): bool
    {
        return ConsumerReceipt::query()
            ->where('tenant_id', $delivery->tenant_id)
            ->where('event_id', $delivery->event_id)
            ->where('consumer_name', $delivery->consumer_name)
            ->exists();
    }
}
