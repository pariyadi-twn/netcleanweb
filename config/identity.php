<?php

return [
    'token_ttl_minutes' => (int) env('IDENTITY_TOKEN_TTL_MINUTES', 720),

    'mfa' => [
        'issuer' => (string) env('IDENTITY_MFA_ISSUER', env('APP_NAME', 'NetClean')),
        'window' => (int) env('IDENTITY_MFA_WINDOW', 1),
        'recovery_code_count' => (int) env('IDENTITY_MFA_RECOVERY_CODES', 8),
    ],
];
