<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CompleteOrderProduction
{
    public function __construct(
        private readonly TransitionProductionTask $transition,
        private readonly AssessOrderReadiness $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Order $order, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $membershipId, $actor): array {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->operational_status, [
                OrderStatus::Completed,
                OrderStatus::PartiallyHandedOver,
                OrderStatus::Cancelled,
            ], true)) {
                throw new ApiException('ORDER_NOT_IN_PRODUCTION', 'The order cannot be completed at this stage.', 409);
            }

            $contents = BagContent::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $locked->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($contents->isEmpty()) {
                throw new ApiException('ORDER_HAS_NO_CONTENT', 'The order has no active bag content.', 409);
            }

            foreach ($contents as $content) {
                $tasks = ProductionTask::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('bag_content_id', $content->id)
                    ->orderByDesc('attempt_no')
                    ->orderBy('sequence')
                    ->orderBy('id')
                    ->get();

                $currentAttempt = (int) $tasks->max('attempt_no');
                $currentTasks = $tasks->where('attempt_no', $currentAttempt)->sortBy('sequence')->values();

                if ($currentTasks->isEmpty()) {
                    throw new ApiException('PRODUCTION_NOT_PLANNED', 'Production tasks have not been planned for this order.', 409);
                }

                $this->ensureQcSatisfied($locked, $content, $currentTasks);

                foreach ($currentTasks as $task) {
                    if (in_array($task->status, [ProductionTaskStatus::Done, ProductionTaskStatus::Skipped], true)) {
                        continue;
                    }

                    if ($task->status === ProductionTaskStatus::Blocked) {
                        throw new ApiException('PRODUCTION_TASK_BLOCKED', 'A blocked production task must be resolved first.', 409);
                    }

                    $before = $task->status;

                    if ($before === ProductionTaskStatus::Queued) {
                        $task = $this->transition->handle(
                            $task,
                            ProductionTaskStatus::InProgress,
                            $task->version,
                            $membershipId,
                            null,
                            $actor,
                        );
                    }

                    if ($task->status === ProductionTaskStatus::InProgress) {
                        $this->transition->handle(
                            $task,
                            ProductionTaskStatus::Done,
                            $task->version,
                            $membershipId,
                            null,
                            $actor,
                        );
                    }
                }
            }

            return $this->readiness->handle($locked);
        });
    }

    /**
     * @param  Collection<int, ProductionTask>  $tasks
     */
    private function ensureQcSatisfied(Order $order, BagContent $content, $tasks): void
    {
        if (! $tasks->where('requires_qc', true)->isNotEmpty()) {
            return;
        }

        $latestQc = QcResult::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('bag_content_id', $content->id)
            ->whereIn('production_task_id', $tasks->pluck('id'))
            ->orderByDesc('id')
            ->first();

        if ($latestQc === null || $latestQc->outcome !== 'passed') {
            throw new ApiException('PRODUCTION_QC_REQUIRED', 'Record a passing QC before completing the order.', 409);
        }
    }
}
