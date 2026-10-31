<?php

namespace Tests\Support;

use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Contracts\OutboxConsumer;
use App\Modules\Operations\Models\OutboxEvent;

class AuditOutboxConsumer implements OutboxConsumer
{
    public function handle(OutboxEvent $event): void
    {
        app(AuditRecorder::class)->record(
            tenantId: $event->tenant_id,
            action: AuditAction::OutboxEventApplied,
            subjectType: AuditRecorder::SUBJECT_OUTBOX_EVENT,
            subjectPublicId: $event->event_id,
            changes: ['event_type' => $event->event_type],
        );
    }
}
