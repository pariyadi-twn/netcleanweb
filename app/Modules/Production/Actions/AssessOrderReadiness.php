<?php

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use Illuminate\Support\Facades\DB;

final class AssessOrderReadiness
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Order $order): array
    {
        $contents = BagContent::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($contents as $content) {
            $tasks = ProductionTask::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('bag_content_id', $content->id)
                ->orderBy('sequence')
                ->orderBy('id')
                ->get();

            $currentAttempt = (int) $tasks->max('attempt_no');
            $currentTasks = $tasks->where('attempt_no', $currentAttempt);
            $currentTaskIds = $currentTasks->pluck('id');
            $requiredOpen = $currentTasks
                ->where('is_required', true)
                ->where('status', '!=', 'done')
                ->isNotEmpty();
            $qcRequired = $currentTasks->where('requires_qc', true)->isNotEmpty();
            $latestQc = QcResult::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('bag_content_id', $content->id)
                ->whereIn('production_task_id', $currentTaskIds)
                ->orderByDesc('id')
                ->first();
            $qcPassed = ! $qcRequired || ($latestQc !== null && $latestQc->outcome === 'passed');

            $rows[] = [
                'bag_content_id' => $content->public_id,
                'ready' => $tasks->isNotEmpty() && ! $requiredOpen && $qcPassed,
                'tasks_done' => ! $requiredOpen && $tasks->isNotEmpty(),
                'qc_required' => $qcRequired,
                'qc_passed' => $qcPassed,
                'latest_qc' => $latestQc === null ? null : [
                    'id' => $latestQc->public_id,
                    'outcome' => $latestQc->outcome,
                    'notes' => $latestQc->notes,
                    'recorded_at' => $latestQc->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
                'attempt_no' => $currentAttempt,
                'task_count' => $currentTasks->count(),
                'open_required_tasks' => $currentTasks->where('is_required', true)->where('status', '!=', 'done')->count(),
            ];
        }

        $allReady = $rows !== [] && collect($rows)->every(fn (array $row): bool => $row['ready']);
        $someReady = collect($rows)->contains(fn (array $row): bool => $row['ready']);

        return [
            'order_id' => $order->public_id,
            'order_number' => $order->order_number,
            'operational_status' => $order->operational_status->value,
            'can_ready' => $allReady,
            'partially_ready' => ! $allReady && $someReady,
            'contents' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $assessment = $this->snapshot($locked);
            $target = $locked->operational_status;

            if (! in_array($locked->operational_status, [OrderStatus::Completed, OrderStatus::PartiallyHandedOver, OrderStatus::Cancelled], true)) {
                $target = $assessment['can_ready']
                    ? OrderStatus::Ready
                    : ($assessment['partially_ready'] ? OrderStatus::PartiallyReady : (($assessment['contents'] ?? []) === [] ? $locked->operational_status : OrderStatus::InProgress));
            }

            if ($target !== $locked->operational_status) {
                $from = $locked->operational_status;
                $locked->forceFill([
                    'operational_status' => $target,
                    'version' => $locked->version + 1,
                ])->save();

                $event = new OrderEvent([
                    'order_id' => $locked->id,
                    'event_type' => 'readiness_changed',
                    'actor_membership_id' => $locked->accepted_by_membership_id,
                    'reason' => null,
                    'payload' => [
                        'from_status' => $from->value,
                        'to_status' => $target->value,
                        'can_ready' => $assessment['can_ready'],
                        'partially_ready' => $assessment['partially_ready'],
                    ],
                ]);
                $event->tenant_id = $locked->tenant_id;
                $event->save();

                $this->auditor->record(
                    tenantId: $locked->tenant_id,
                    action: AuditAction::OrderReadinessChanged,
                    subjectType: AuditRecorder::SUBJECT_ORDER,
                    subjectPublicId: $locked->public_id,
                    changes: ['from_status' => $from->value, 'to_status' => $target->value],
                );
                $this->outbox->record(
                    tenantId: $locked->tenant_id,
                    eventType: 'order.readiness_changed',
                    aggregatePublicId: $locked->public_id,
                    payload: [
                        'order_id' => $locked->public_id,
                        'status' => $target->value,
                    ],
                );
            }

            $assessment['operational_status'] = $target->value;

            return $assessment;
        });
    }
}
