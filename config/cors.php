<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(explode(',', (string) env('WEB_ALLOWED_ORIGINS', '')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Tenant-ID', 'X-Correlation-ID', 'Idempotency-Key', 'X-XSRF-TOKEN'],
    'exposed_headers' => ['X-Correlation-ID', 'Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
