<?php

namespace App\Modules\Operations\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OpsMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'requests' => $this->requests(),
            'database' => $this->database(),
            'queue' => $this->queue(),
            'outbox' => $this->outbox(),
            'storage' => $this->storage(),
            'backup' => $this->backup(),
        ];
    }

    /**
     * @return array{requests: int, errors: int, latency_avg_ms: float}
     */
    private function requests(): array
    {
        $requests = 0;
        $errors = 0;
        $latency = 0;
        $now = CarbonImmutable::now()->utc();

        for ($minute = 0; $minute < 5; $minute++) {
            $key = $now->subMinutes($minute)->format('YmdHi');
            $requests += (int) Cache::get('ops:requests:'.$key, 0);
            $errors += (int) Cache::get('ops:errors:'.$key, 0);
            $latency += (int) Cache::get('ops:latency:'.$key, 0);
        }

        return [
            'requests' => $requests,
            'errors' => $errors,
            'latency_avg_ms' => $requests === 0 ? 0.0 : round($latency / $requests, 2),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function database(): array
    {
        $activity = DB::selectOne("SELECT count(*) AS total, count(*) FILTER (WHERE state = 'active') AS active, count(*) FILTER (WHERE wait_event_type = 'Lock') AS waiting, coalesce(max(extract(epoch FROM now() - xact_start)), 0) AS longest_transaction FROM pg_stat_activity WHERE datname = current_database()");
        $locks = DB::selectOne('SELECT count(*) AS waiting FROM pg_locks WHERE NOT granted');

        return [
            'connections' => (int) ($activity->total ?? 0),
            'active' => (int) ($activity->active ?? 0),
            'waiting_locks' => (int) ($locks->waiting ?? 0),
            'longest_transaction_seconds' => (int) round((float) ($activity->longest_transaction ?? 0)),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function queue(): array
    {
        $row = DB::selectOne('SELECT count(*) AS pending, coalesce(max(extract(epoch FROM now() - to_timestamp(created_at))), 0) AS oldest FROM jobs WHERE reserved_at IS NULL');

        return [
            'pending' => (int) ($row->pending ?? 0),
            'oldest_age_seconds' => (int) round((float) ($row->oldest ?? 0)),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function outbox(): array
    {
        $row = DB::selectOne('SELECT count(*) AS pending, coalesce(max(extract(epoch FROM now() - ed.created_at)), 0) AS oldest FROM event_deliveries ed WHERE NOT EXISTS (SELECT 1 FROM consumer_receipts cr WHERE cr.tenant_id = ed.tenant_id AND cr.event_id = ed.event_id AND cr.consumer_name = ed.consumer_name)');

        return [
            'without_receipt' => (int) ($row->pending ?? 0),
            'oldest_age_seconds' => (int) round((float) ($row->oldest ?? 0)),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function storage(): array
    {
        $path = storage_path('app/private');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'free_bytes' => $free === false ? null : (int) $free,
            'total_bytes' => $total === false ? null : (int) $total,
            'used_percent' => $free === false || $total === false || $total === 0
                ? null
                : round((1 - ($free / $total)) * 100, 2),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function backup(): array
    {
        $directory = (string) config('ops.backup_path');
        $files = glob($directory.'/*.enc') ?: [];
        if ($files === []) {
            return ['latest_age_seconds' => null];
        }
        $latest = max(array_map('filemtime', $files));

        return ['latest_age_seconds' => max(0, time() - (int) $latest)];
    }
}
