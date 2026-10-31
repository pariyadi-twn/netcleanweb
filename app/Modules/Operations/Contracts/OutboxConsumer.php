<?php

namespace App\Modules\Operations\Contracts;

use App\Modules\Operations\Models\OutboxEvent;

interface OutboxConsumer
{
    public function handle(OutboxEvent $event): void;
}
