<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\InvoiceAdjustmentKind;
use App\Modules\Order\Enums\OrderRevisionStatus;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\InvoiceAdjustment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\OrderRevision;
use Illuminate\Support\Facades\DB;

final class ApplyOrderRevision
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        OrderRevision $revision,
        int $approverMembershipId,
        ?User $actor,
        string $decisionReason,
    ): Order {
        return DB::transaction(function () use ($revision, $approverMembershipId, $actor, $decisionReason): Order {
            $lockedRevision = OrderRevision::query()
                ->where('tenant_id', $revision->tenant_id)
                ->whereKey($revision->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->where('tenant_id', $lockedRevision->tenant_id)
                ->whereKey($lockedRevision->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRevision->status === OrderRevisionStatus::Applied) {
                return $order;
            }

            if ($lockedRevision->status !== OrderRevisionStatus::Pending) {
                throw new ApiException('ORDER_REVISION_DECIDED', 'The order revision has already been decided.', 409);
            }

            if ($order->version !== $lockedRevision->expected_version) {
                throw new ApiException('VERSION_CONFLICT', 'The order changed before the revision was applied.', 409);
            }

            $payload = $lockedRevision->payload;
            $invoice = Invoice::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_id', $order->id)
                ->firstOrFail();
            $services = Service::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereIn('public_id', collect($payload['new_lines'])->pluck('service_id')->unique()->values())
                ->get()
                ->keyBy('public_id');

            foreach ($payload['new_lines'] as $line) {
                $orderLine = OrderLine::query()
                    ->where('tenant_id', $order->tenant_id)
                    ->where('order_id', $order->id)
                    ->where('public_id', $line['id'])
                    ->firstOrFail();
                $service = $services->get($line['service_id']);

                if ($service === null) {
                    throw new ApiException('QUOTE_SERVICE_UNAVAILABLE', 'A revised service is no longer available.', 409);
                }

                $orderLine->forceFill([
                    'service_id' => $service->id,
                    'unit' => $line['unit'],
                    'measured_quantity' => $line['measured_quantity'],
                    'billable_quantity' => $line['billable_quantity'],
                    'unit_price_rupiah' => $line['unit_price_rupiah'],
                    'line_total_rupiah' => $line['line_total_rupiah'],
                    'service_snapshot' => [
                        ...$line['service_snapshot'],
                        'pricing' => $line['pricing'],
                        'measured_quantity' => $line['measured_quantity'],
                        'billable_quantity' => $line['billable_quantity'],
                        'base_total_rupiah' => $line['base_total_rupiah'],
                        'discount_rupiah' => $line['discount_rupiah'],
                        'express_surcharge_rupiah' => $line['express_surcharge_rupiah'],
                        'line_total_rupiah' => $line['line_total_rupiah'],
                    ],
                    'version' => $orderLine->version + 1,
                ])->save();
            }

            $difference = $lockedRevision->difference_rupiah;
            $adjustment = new InvoiceAdjustment([
                'invoice_id' => $invoice->id,
                'order_revision_id' => $lockedRevision->id,
                'kind' => $difference > 0 ? InvoiceAdjustmentKind::DebitNote : InvoiceAdjustmentKind::CreditNote,
                'amount_rupiah' => $difference,
                'reason' => $lockedRevision->reason,
                'requested_by_membership_id' => $lockedRevision->requested_by_membership_id,
                'approved_by_membership_id' => $approverMembershipId,
            ]);
            $adjustment->tenant_id = $order->tenant_id;
            $adjustment->save();

            $event = new OrderEvent([
                'order_id' => $order->id,
                'event_type' => 'revised',
                'actor_membership_id' => $approverMembershipId,
                'reason' => $lockedRevision->reason,
                'payload' => [
                    'revision_id' => $lockedRevision->public_id,
                    'old_lines' => $payload['old_lines'],
                    'new_lines' => $payload['new_lines'],
                    'old_total_rupiah' => $payload['old_total_rupiah'],
                    'new_total_rupiah' => $payload['new_total_rupiah'],
                    'difference_rupiah' => $difference,
                ],
            ]);
            $event->tenant_id = $order->tenant_id;
            $event->save();

            $order->forceFill(['version' => $order->version + 1])->save();

            $lockedRevision->forceFill([
                'status' => OrderRevisionStatus::Applied,
                'approved_by_membership_id' => $approverMembershipId,
                'decision_reason' => $decisionReason,
                'decided_at' => now(),
                'applied_at' => now(),
                'invoice_adjustment_id' => $adjustment->id,
                'version' => $lockedRevision->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $order->tenant_id,
                action: AuditAction::OrderRevised,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $order->public_id,
                changes: [
                    'revision_id' => $lockedRevision->public_id,
                    'difference_rupiah' => $difference,
                    'old_total_rupiah' => $payload['old_total_rupiah'],
                    'new_total_rupiah' => $payload['new_total_rupiah'],
                    'reason' => $lockedRevision->reason,
                ],
                actor: $actor,
                reason: $lockedRevision->reason,
            );

            $this->outbox->record(
                tenantId: $order->tenant_id,
                eventType: 'order.revised',
                aggregatePublicId: $order->public_id,
                payload: [
                    'order_id' => $order->public_id,
                    'revision_id' => $lockedRevision->public_id,
                    'difference_rupiah' => $difference,
                    'new_total_rupiah' => $payload['new_total_rupiah'],
                ],
            );

            return $order->refresh();
        });
    }
}
