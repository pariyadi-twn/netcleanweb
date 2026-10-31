<?php

namespace App\Modules\Operations\Actions;

use App\Exceptions\ApiException;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;

final class GetDeadlinePromise
{
    /**
     * @return array<string, mixed>
     */
    public function handle(int $tenantId, string $promisePublicId): array
    {
        $history = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('subject_type', AuditRecorder::SUBJECT_DEADLINE_PROMISE)
            ->where('subject_public_id', $promisePublicId)
            ->whereIn('action', [AuditAction::DeadlinePromiseRecorded->value, AuditAction::DeadlineRevised->value])
            ->orderBy('id')
            ->get();

        if ($history->isEmpty()) {
            throw new ApiException('DEADLINE_PROMISE_NOT_FOUND', 'The deadline promise was not found.', 404);
        }

        $events = $history->map(fn (AuditLog $log): array => [
            'action' => $log->action->value,
            'old_due_at' => $log->redacted_changes['old_due_at'] ?? null,
            'new_due_at' => $log->redacted_changes['due_at'] ?? null,
            'reason' => $log->reason,
            'actor_user_id' => $log->actor_user_id,
            'correlation_id' => $log->correlation_id,
            'created_at' => $log->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);

        return [
            'promise_id' => $promisePublicId,
            'due_at_original' => $events->first()['new_due_at'],
            'due_at_current' => $events->last()['new_due_at'],
            'history' => $events->all(),
        ];
    }
}
