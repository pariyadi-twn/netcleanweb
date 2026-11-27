<?php

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;

final class OrderProductionSummary
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Order $order): array
    {
        $tenant = Tenant::query()->whereKey($order->tenant_id)->first();
        $invoice = $order->invoice;
        $balance = $invoice === null ? 0 : $invoice->totalRupiah() - $invoice->netAllocatedRupiah();

        $contents = BagContent::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('is_active', true)
            ->pluck('id');

        $tasks = ProductionTask::query()
            ->where('tenant_id', $order->tenant_id)
            ->whereIn('bag_content_id', $contents)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $currentAttemptByContent = $tasks->groupBy('bag_content_id')
            ->map(fn ($rows): int => (int) $rows->max('attempt_no'));
        $currentTasks = $tasks->filter(
            fn (ProductionTask $task): bool => $currentAttemptByContent->get($task->bag_content_id) === $task->attempt_no,
        );

        $required = $currentTasks->where('is_required', true);
        $requiredDone = $required->where('status', 'done')->count();
        $qcRequired = $currentTasks->where('requires_qc', true)->isNotEmpty();
        $qcPassed = ! $qcRequired || QcResult::query()
            ->where('tenant_id', $order->tenant_id)
            ->whereIn('production_task_id', $currentTasks->pluck('id'))
            ->orderByDesc('id')
            ->value('outcome') === 'passed';

        $stage = match (true) {
            $order->operational_status === OrderStatus::Cancelled => 'cancelled',
            in_array($order->operational_status, [OrderStatus::Completed, OrderStatus::PartiallyHandedOver], true) => 'handed_over',
            $currentTasks->isEmpty() => 'received',
            $required->isNotEmpty() && $requiredDone === $required->count() && $qcPassed => 'ready',
            default => 'processing',
        };

        $nextAction = match (true) {
            $stage === 'received' => 'plan',
            $stage === 'processing' => $requiredDone < $required->count() ? 'complete' : ($qcPassed ? 'handover' : 'qc'),
            $stage === 'ready' => 'handover',
            default => null,
        };

        return [
            'order_id' => $order->public_id,
            'order_number' => $order->order_number,
            'operational_status' => $order->operational_status->value,
            'stage' => $stage,
            'stage_label' => match ($stage) {
                'received' => 'Diterima',
                'processing' => 'Diproses',
                'ready' => 'Siap diambil',
                'handed_over' => 'Diserahkan',
                'cancelled' => 'Dibatalkan',
                default => $stage,
            },
            'progress' => [
                'done' => $requiredDone,
                'total' => $required->count(),
            ],
            'qc_required' => $qcRequired,
            'qc_passed' => $qcPassed,
            'next_action' => $nextAction,
            'production_mode' => $tenant?->production_mode?->value ?? 'detailed',
            'balance_due_rupiah' => max(0, $balance),
            'can_pay_later' => ! (bool) $tenant?->require_payment_before_handover,
        ];
    }
}
