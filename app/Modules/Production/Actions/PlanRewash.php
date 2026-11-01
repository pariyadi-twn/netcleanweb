<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\Complaint;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Support\Facades\DB;

final class PlanRewash
{
    public function __construct(
        private readonly AssessOrderReadiness $readiness,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return list<ProductionTask>
     */
    public function handle(Complaint $complaint, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($complaint, $actor): array {
            $locked = Complaint::query()
                ->where('tenant_id', $complaint->tenant_id)
                ->whereKey($complaint->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->kind !== 'rewash') {
                throw new ApiException('COMPLAINT_NOT_REWASH', 'Only a rewash complaint can create rewash tasks.', 409);
            }
            if (in_array($locked->status, ['resolved', 'rejected'], true)) {
                throw new ApiException('COMPLAINT_ALREADY_CLOSED', 'The complaint is already closed.', 409);
            }

            $order = Order::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->rewash_attempt_no !== null) {
                return ProductionTask::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->whereIn('bag_content_id', BagContent::query()
                        ->where('tenant_id', $locked->tenant_id)
                        ->where('order_id', $order->id)
                        ->when($locked->order_line_id, fn ($query, $lineId) => $query->where('order_line_id', $lineId))
                        ->select('id'))
                    ->where('attempt_no', $locked->rewash_attempt_no)
                    ->orderBy('id')
                    ->get()
                    ->all();
            }

            $contents = BagContent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $order->id)
                ->when($locked->order_line_id, fn ($query, $lineId) => $query->where('order_line_id', $lineId))
                ->where('is_active', true)
                ->get();
            if ($contents->isEmpty()) {
                throw new ApiException('REWASH_CONTENT_MISSING', 'No active bag content matches this complaint.', 409);
            }

            $created = [];
            $attempt = null;
            foreach ($contents as $content) {
                $latestAttempt = ProductionTask::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('bag_content_id', $content->id)
                    ->max('attempt_no');
                if ($latestAttempt === null) {
                    continue;
                }
                $attempt = max($attempt ?? 0, (int) $latestAttempt + 1);
                $source = ProductionTask::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('bag_content_id', $content->id)
                    ->where('attempt_no', $latestAttempt)
                    ->orderBy('sequence')
                    ->get();
                foreach ($source as $task) {
                    $rewash = new ProductionTask([
                        'bag_content_id' => $content->id,
                        'production_location_id' => $task->production_location_id,
                        'step_code' => $task->step_code,
                        'sequence' => $task->sequence,
                        'is_required' => $task->is_required,
                        'requires_qc' => $task->requires_qc,
                        'attempt_no' => (int) $latestAttempt + 1,
                        'original_task_id' => $task->original_task_id ?? $task->id,
                        'status' => 'queued',
                        'due_at' => $order->due_at_current,
                    ]);
                    $rewash->tenant_id = $locked->tenant_id;
                    $rewash->save();
                    $created[] = $rewash;
                }
            }

            if ($created === []) {
                throw new ApiException('REWASH_TASKS_MISSING', 'There is no completed production attempt to rewash.', 409);
            }

            $locked->forceFill([
                'status' => 'investigating',
                'rewash_attempt_no' => $attempt,
                'version' => $locked->version + 1,
            ])->save();

            $this->readiness->handle($order);

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ComplaintRewashPlanned,
                subjectType: AuditRecorder::SUBJECT_COMPLAINT,
                subjectPublicId: $locked->public_id,
                changes: ['attempt_no' => $attempt, 'task_count' => count($created)],
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'complaint.rewash_planned',
                aggregatePublicId: $locked->public_id,
                payload: ['complaint_id' => $locked->public_id, 'attempt_no' => $attempt, 'task_count' => count($created)],
            );

            return $created;
        });
    }
}
