<?php

return [
    'disk' => env('UPLOAD_DISK', 'local'),
    'max_bytes' => 5 * 1024 * 1024,
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_dimension' => 10000,
    'thumbnail_max' => 320,
    'signed_url_minutes' => 5,
    'pending_ttl_hours' => 24,
    'quarantine_ttl_days' => 7,
];
