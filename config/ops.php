<?php

return [
    'metrics_token' => env('OPS_METRICS_TOKEN'),
    'backup_path' => env('OPS_BACKUP_PATH', storage_path('app/backups')),
    'probe_timeout_seconds' => (int) env('OPS_PROBE_TIMEOUT_SECONDS', 5),
    'thresholds' => [
        'backup_age_hours' => 36,
        'queue_age_seconds' => 900,
        'outbox_age_seconds' => 900,
        'db_waiting_locks' => 1,
    ],
];
