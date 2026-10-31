<?php

namespace App\Modules\Operations\Outbox;

use App\Modules\Operations\Contracts\OutboxConsumer;
use RuntimeException;

final class OutboxConsumerRegistry
{
    public function for(string $name): OutboxConsumer
    {
        $consumers = config('outbox.consumers', []);
        $consumerClass = is_array($consumers) ? ($consumers[$name] ?? null) : null;

        if (! is_string($consumerClass) || ! is_a($consumerClass, OutboxConsumer::class, true)) {
            throw new RuntimeException('Outbox consumer is not configured: '.$name);
        }

        return app($consumerClass);
    }
}
