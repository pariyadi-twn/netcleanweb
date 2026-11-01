<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
    'whatsapp_endpoint' => env('WHATSAPP_NOTIFICATION_ENDPOINT'),
    'whatsapp_token' => env('WHATSAPP_NOTIFICATION_TOKEN'),
    'email_from' => env('NOTIFICATION_EMAIL_FROM', 'no-reply@netclean.local'),
    'retry_seconds' => [30, 120, 300],
];
