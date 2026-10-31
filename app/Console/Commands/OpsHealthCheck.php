<?php

namespace App\Console\Commands;

use App\Modules\Operations\Support\OpsHealth;
use App\Modules\Operations\Support\OpsMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ops:health-check')]
#[Description('Fail when health, queue, outbox, lock, or backup age crosses thresholds')]
class OpsHealthCheck extends Command
{
    public function handle(OpsHealth $health, OpsMetrics $metrics): int
    {
        $healthResult = $health->check();
        $snapshot = $metrics->snapshot();
        $thresholds = config('ops.thresholds');
        $failures = [];

        if ($healthResult['status'] !== 'ok') {
            $failures[] = 'health: '.json_encode($healthResult['checks']);
        }
        if ($snapshot['database']['waiting_locks'] >= $thresholds['db_waiting_locks']) {
            $failures[] = 'database locks: '.$snapshot['database']['waiting_locks'];
        }
        if ($snapshot['queue']['oldest_age_seconds'] > $thresholds['queue_age_seconds']) {
            $failures[] = 'queue age: '.$snapshot['queue']['oldest_age_seconds'];
        }
        if ($snapshot['outbox']['oldest_age_seconds'] > $thresholds['outbox_age_seconds']) {
            $failures[] = 'outbox age: '.$snapshot['outbox']['oldest_age_seconds'];
        }
        $backupAge = $snapshot['backup']['latest_age_seconds'];
        if ($backupAge === null || $backupAge > $thresholds['backup_age_hours'] * 3600) {
            $failures[] = 'backup age: '.($backupAge ?? 'missing');
        }

        if ($failures !== []) {
            $this->error(implode('; ', $failures));

            return self::FAILURE;
        }

        $this->info('Operations health is within thresholds.');

        return self::SUCCESS;
    }
}
