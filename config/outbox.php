<?php

return [
    'lease_seconds' => (int) env('OUTBOX_LEASE_SECONDS', 60),
    'retry_seconds' => (int) env('OUTBOX_RETRY_SECONDS', 30),
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

    'event_types' => [
        'membership.assignments_updated',
        'order.accepted',
        'order.revised',
        'payment.recorded',
        'refund.requested',
        'refund.decided',
        'refund.approved',
        'refund.processing',
        'refund.succeeded',
        'refund.failed',
        'refund.rejected',
        'refund.cancelled',
        'allocation.reversed',
        'expense.created',
        'expense.approved',
        'expense.rejected',
        'cash_transfer.confirmed',
        'order.cancelled',
        'import.completed',
        'export.ready',
        'support_grant.created',
        'support_grant.revoked',
        'production.tasks_planned',
        'production.task_transitioned',
        'production.qc_recorded',
        'order.readiness_changed',
        'complaint.created',
        'complaint.resolved',
        'complaint.rejected',
        'complaint.rewash_planned',
        'order.handed_over',
    ],

    'consumers' => [
        // 'audit_trail' => \App\Modules\Operations\Consumers\AuditTrailConsumer::class,
    ],

    'subscriptions' => [
        // 'membership.assignments_updated' => ['audit_trail'],
    ],
];
