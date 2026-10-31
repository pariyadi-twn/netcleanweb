<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Location;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanProductionTasks
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return list<ProductionTask>
     */
    public function handle(Order $order, Location $location, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $location, $actor): array {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->operational_status->value === 'cancelled') {
                throw new ApiException('ORDER_CANCELLED', 'Cancelled orders cannot be planned.', 409);
            }
            if ($location->tenant_id !== $locked->tenant_id || ! $this->context->canAccessLocation($location)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $contentIds = $locked->bags()->with('contents')->get()->flatMap->contents->where('is_active', true)->pluck('id');
            if ($contentIds->isEmpty()) {
                throw ValidationException::withMessages(['order' => ['The order has no active bag content.']]);
            }

            $existing = ProductionTask::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('bag_content_id', $contentIds)
                ->orderBy('id')
                ->get();
            if ($existing->isNotEmpty()) {
                if ($existing->every(fn (ProductionTask $task): bool => $task->production_location_id === $location->id)) {
                    return $existing->values()->all();
                }

                throw new ApiException('PRODUCTION_ALREADY_PLANNED', 'Production tasks are already planned at another location.', 409);
            }

            $contents = $locked->bags()->with(['contents.orderLine'])->get()->flatMap->contents->where('is_active', true);
            $created = [];
            foreach ($contents as $content) {
                $steps = $content->orderLine?->service_snapshot['workflow']['steps'] ?? [];
                if (! is_array($steps) || $steps === []) {
                    throw ValidationException::withMessages(['order' => ['A service workflow snapshot is missing.']]);
                }

                usort($steps, fn (array $a, array $b): int => ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));
                foreach ($steps as $step) {
                    $task = new ProductionTask([
                        'bag_content_id' => $content->id,
                        'production_location_id' => $location->id,
                        'step_code' => (string) $step['code'],
                        'sequence' => (int) $step['sequence'],
                        'is_required' => (bool) ($step['is_required'] ?? true),
                        'requires_qc' => (bool) ($step['requires_qc'] ?? false),
                        'attempt_no' => 1,
                        'status' => ProductionTaskStatus::Queued,
                        'due_at' => $locked->due_at_current,
                    ]);
                    $task->tenant_id = $locked->tenant_id;
                    $task->save();
                    $created[] = $task;
                }
            }

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ProductionTasksPlanned,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $locked->public_id,
                changes: ['location_id' => $location->public_id, 'task_count' => count($created)],
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'production.tasks_planned',
                aggregatePublicId: $locked->public_id,
                payload: [
                    'order_id' => $locked->public_id,
                    'location_id' => $location->public_id,
                    'task_count' => count($created),
                ],
            );

            return $created;
        });
    }
}
