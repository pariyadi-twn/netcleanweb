<?php

return [
    'lease_seconds' => (int) env('OUTBOX_LEASE_SECONDS', 60),
    'retry_seconds' => (int) env('OUTBOX_RETRY_SECONDS', 30),
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

    'event_types' => [
        'membership.assignments_updated',
        'order.accepted',
        'order.revised',
    ],

    'consumers' => [
        // 'audit_trail' => \App\Modules\Operations\Consumers\AuditTrailConsumer::class,
    ],

    'subscriptions' => [
        // 'membership.assignments_updated' => ['audit_trail'],
    ],
];
