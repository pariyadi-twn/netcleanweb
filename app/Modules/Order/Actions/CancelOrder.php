<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use Illuminate\Support\Facades\DB;

final class CancelOrder
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return array{order: Order, event: OrderEvent}
     */
    public function handle(Order $order, int $expectedVersion, string $reason, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $expectedVersion, $reason, $membershipId, $actor): array {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->version !== $expectedVersion) {
                throw new ApiException('ORDER_VERSION_CONFLICT', 'The order has changed since it was loaded.', 409);
            }

            if (in_array($locked->operational_status, [OrderStatus::Completed, OrderStatus::PartiallyHandedOver, OrderStatus::Cancelled], true)) {
                throw new ApiException('ORDER_CANCEL_NOT_ALLOWED', 'The order cannot be cancelled at this stage.', 409);
            }

            $locked->loadMissing(['invoice.adjustments', 'invoice.allocations', 'bags']);
            $handedOver = $locked->bags->contains(fn ($bag): bool => $bag->custody_status === 'handed_over');
            if ($handedOver) {
                throw new ApiException('ORDER_GOODS_HANDED_OVER', 'An order with handed-over bags cannot be cancelled.', 409);
            }

            $invoice = $locked->invoice;
            $total = $invoice?->totalRupiah() ?? 0;
            $paid = $invoice?->netAllocatedRupiah() ?? 0;
            $balance = $total - $paid;
            $refundRequired = max(0, $paid);

            $from = $locked->operational_status;
            $locked->forceFill([
                'operational_status' => OrderStatus::Cancelled,
                'version' => $locked->version + 1,
            ])->save();

            $event = new OrderEvent([
                'order_id' => $locked->id,
                'event_type' => 'cancelled',
                'actor_membership_id' => $membershipId,
                'reason' => $reason,
                'payload' => [
                    'from_status' => $from->value,
                    'to_status' => OrderStatus::Cancelled->value,
                    'invoice_id' => $invoice?->public_id,
                    'total_rupiah' => $total,
                    'paid_rupiah' => $paid,
                    'balance_due_rupiah' => $balance,
                    'refund_required_rupiah' => $refundRequired,
                    'bags' => $locked->bags->map(fn ($bag): array => [
                        'id' => $bag->public_id,
                        'custody_status' => $bag->custody_status,
                    ])->values()->all(),
                ],
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::OrderCancelled,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $locked->public_id,
                changes: ['from_status' => $from->value, 'refund_required_rupiah' => $refundRequired],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'order.cancelled',
                aggregatePublicId: $locked->public_id,
                payload: [
                    'order_id' => $locked->public_id,
                    'invoice_id' => $invoice?->public_id,
                    'paid_rupiah' => $paid,
                    'refund_required_rupiah' => $refundRequired,
                ],
            );

            return ['order' => $locked->refresh(), 'event' => $event->refresh()];
        });
    }
}
