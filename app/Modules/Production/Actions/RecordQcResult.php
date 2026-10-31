<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordQcResult
{
    public function __construct(
        private readonly AssessOrderReadiness $readiness,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(
        ProductionTask $task,
        string $outcome,
        array $checklist,
        ?string $notes,
        int $membershipId,
        ?User $actor = null,
    ): array {
        return DB::transaction(function () use ($task, $outcome, $checklist, $notes, $membershipId, $actor): array {
            $locked = ProductionTask::query()
                ->where('tenant_id', $task->tenant_id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->context->canAccessLocation($locked->location)) {
                throw new AuthorizationException('This action is unauthorized.');
            }
            if ($locked->status->value !== 'done' || ! $locked->requires_qc) {
                throw new ApiException('QC_TASK_NOT_READY', 'QC can only be recorded for a completed QC task.', 409);
            }
            if ($outcome === 'failed' && trim((string) $notes) === '') {
                throw ValidationException::withMessages(['notes' => ['Notes are required when QC fails.']]);
            }

            $result = new QcResult([
                'bag_content_id' => $locked->bag_content_id,
                'production_task_id' => $locked->id,
                'inspector_membership_id' => $membershipId,
                'outcome' => $outcome,
                'checklist' => $checklist,
                'notes' => $notes,
                'attempt_no' => $locked->attempt_no,
            ]);
            $result->tenant_id = $locked->tenant_id;
            $result->save();

            $order = Order::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->content->order_id)
                ->firstOrFail();
            $readiness = $this->readiness->handle($order);

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ProductionQcRecorded,
                subjectType: AuditRecorder::SUBJECT_PRODUCTION_TASK,
                subjectPublicId: $locked->public_id,
                changes: ['outcome' => $outcome, 'attempt_no' => $locked->attempt_no],
                reason: $notes,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'production.qc_recorded',
                aggregatePublicId: $result->public_id,
                payload: [
                    'qc_result_id' => $result->public_id,
                    'production_task_id' => $locked->public_id,
                    'outcome' => $outcome,
                ],
            );

            return ['qc' => $result->refresh(), 'readiness' => $readiness];
        });
    }
}
