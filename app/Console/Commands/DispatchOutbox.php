<?php

namespace App\Console\Commands;

use App\Modules\Operations\Outbox\OutboxDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:dispatch {--limit= : Maximum number of pending deliveries to claim}')]
#[Description('Claim pending outbox deliveries and dispatch their processing jobs')]
class DispatchOutbox extends Command
{
    public function handle(OutboxDispatcher $dispatcher): int
    {
        $limitOption = $this->option('limit');

        if ($limitOption !== null && (! is_string($limitOption) || ! ctype_digit($limitOption) || (int) $limitOption < 1 || (int) $limitOption > 1000)) {
            $this->error('The --limit option must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $dispatched = $dispatcher->dispatch($limitOption === null ? null : (int) $limitOption);

        $this->info("Dispatched {$dispatched} outbox delivery job(s).");

        return self::SUCCESS;
    }
}
