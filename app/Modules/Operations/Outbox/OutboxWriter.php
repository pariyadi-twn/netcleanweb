<?php

namespace App\Modules\Operations\Outbox;

use App\Modules\Operations\Contracts\OutboxConsumer;
use App\Modules\Operations\Models\EventDelivery;
use App\Modules\Operations\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OutboxWriter
{
    private const SENSITIVE_KEY_PATTERN = '/pass(word)?|token|secret|mfa|recovery|authorization|api[_-]?key|credential/i';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(int $tenantId, string $eventType, string $aggregatePublicId, array $payload): OutboxEvent
    {
        if (! in_array($eventType, (array) config('outbox.event_types', []), true)) {
            throw new InvalidArgumentException('Unsupported outbox event type.');
        }

        if (! Str::isUuid($aggregatePublicId)) {
            throw new InvalidArgumentException('The aggregate public ID must be a UUID.');
        }

        $this->assertSafePayload($payload);

        $subscriptions = config('outbox.subscriptions', []);
        $consumers = is_array($subscriptions) ? ($subscriptions[$eventType] ?? []) : [];

        if (! is_array($consumers)) {
            throw new InvalidArgumentException('Outbox subscriptions must be an array.');
        }

        foreach ($consumers as $consumer) {
            $registeredConsumers = config('outbox.consumers', []);
            $consumerClass = is_string($consumer) && is_array($registeredConsumers)
                ? ($registeredConsumers[$consumer] ?? null)
                : null;

            if (! is_string($consumer) || ! is_string($consumerClass) || ! is_a($consumerClass, OutboxConsumer::class, true)) {
                throw new InvalidArgumentException('Outbox consumer is not configured: '.$consumer);
            }
        }

        return DB::transaction(function () use ($tenantId, $eventType, $aggregatePublicId, $payload, $consumers): OutboxEvent {
            $event = OutboxEvent::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => (string) Str::uuid(),
                'event_type' => $eventType,
                'aggregate_public_id' => $aggregatePublicId,
                'payload' => $payload,
            ]);

            foreach ($consumers as $consumer) {
                EventDelivery::query()->create([
                    'tenant_id' => $tenantId,
                    'event_id' => $event->event_id,
                    'consumer_name' => $consumer,
                    'attempts' => 0,
                    'next_attempt_at' => now(),
                ]);
            }

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSafePayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                throw new InvalidArgumentException('Outbox payload must not contain sensitive fields.');
            }

            if (is_array($value)) {
                $this->assertSafePayload($value);
            }
        }
    }
}
