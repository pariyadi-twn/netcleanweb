<?php

return [
    'export_expiry_hours' => (int) env('REPORT_EXPORT_EXPIRY_HOURS', 24),
    'max_active_exports' => (int) env('REPORT_MAX_ACTIVE_EXPORTS', 3),
    'export_queue' => env('REPORT_EXPORT_QUEUE', 'exports'),
];
