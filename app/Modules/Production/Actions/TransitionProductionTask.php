<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\ProductionTaskEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionProductionTask
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
        private readonly AssessOrderReadiness $readiness,
    ) {}

    public function handle(
        ProductionTask $task,
        ProductionTaskStatus $to,
        int $expectedVersion,
        int $membershipId,
        ?string $reason = null,
        ?User $actor = null,
    ): ProductionTask {
        return DB::transaction(function () use ($task, $to, $expectedVersion, $membershipId, $reason, $actor): ProductionTask {
            $locked = ProductionTask::query()
                ->where('tenant_id', $task->tenant_id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->version !== $expectedVersion) {
                throw new ApiException('PRODUCTION_TASK_VERSION_CONFLICT', 'The production task has changed.', 409);
            }
            if (! $this->context->canAccessLocation($locked->location)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $from = $locked->status;
            $allowed = match ($from) {
                ProductionTaskStatus::Queued => [ProductionTaskStatus::InProgress, ProductionTaskStatus::Blocked, ProductionTaskStatus::Skipped],
                ProductionTaskStatus::InProgress => [ProductionTaskStatus::Done, ProductionTaskStatus::Blocked, ProductionTaskStatus::Skipped],
                ProductionTaskStatus::Blocked => [ProductionTaskStatus::Queued],
                default => [],
            };
            if (! in_array($to, $allowed, true)) {
                throw new ApiException('PRODUCTION_TRANSITION_NOT_ALLOWED', 'The production status transition is not allowed.', 409);
            }
            if ((in_array($to, [ProductionTaskStatus::Blocked, ProductionTaskStatus::Skipped], true) || $from === ProductionTaskStatus::Blocked) && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => ['A reason is required for blocked or skipped tasks.']]);
            }

            if (in_array($to, [ProductionTaskStatus::InProgress, ProductionTaskStatus::Done], true)) {
                $previousOpen = ProductionTask::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('bag_content_id', $locked->bag_content_id)
                    ->where('sequence', '<', $locked->sequence)
                    ->whereNotIn('status', [ProductionTaskStatus::Done->value, ProductionTaskStatus::Skipped->value])
                    ->exists();
                if ($previousOpen) {
                    throw new ApiException('PRODUCTION_PREREQUISITE_OPEN', 'A previous production step is not complete.', 409);
                }

                if ($to === ProductionTaskStatus::InProgress) {
                    $another = ProductionTask::query()
                        ->where('tenant_id', $locked->tenant_id)
                        ->where('bag_content_id', $locked->bag_content_id)
                        ->where('status', ProductionTaskStatus::InProgress->value)
                        ->whereKeyNot($locked->id)
                        ->exists();
                    if ($another) {
                        throw new ApiException('PRODUCTION_TASK_BUSY', 'Another task for this bag content is already in progress.', 409);
                    }
                }
            }

            $locked->forceFill([
                'status' => $to,
                'started_at' => $to === ProductionTaskStatus::InProgress ? ($locked->started_at ?? now()) : $locked->started_at,
                'completed_at' => $to === ProductionTaskStatus::Done ? now() : $locked->completed_at,
                'version' => $locked->version + 1,
            ])->save();

            $event = new ProductionTaskEvent([
                'production_task_id' => $locked->id,
                'actor_membership_id' => $membershipId,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ProductionTaskTransitioned,
                subjectType: AuditRecorder::SUBJECT_PRODUCTION_TASK,
                subjectPublicId: $locked->public_id,
                changes: ['from_status' => $from->value, 'to_status' => $to->value, 'step_code' => $locked->step_code],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'production.task_transitioned',
                aggregatePublicId: $locked->public_id,
                payload: [
                    'production_task_id' => $locked->public_id,
                    'step_code' => $locked->step_code,
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                ],
            );

            $order = Order::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->content->order_id)
                ->firstOrFail();
            $this->readiness->handle($order);

            return $locked->refresh();
        });
    }
}
