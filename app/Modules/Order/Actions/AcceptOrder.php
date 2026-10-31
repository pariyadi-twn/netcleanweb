<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Actions\EnsureBranchAcceptsOrders;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\FulfillmentMethod;
use App\Modules\Order\Enums\InvoiceDocumentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\InvoiceLine;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Order\Support\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcceptOrder
{
    public function __construct(
        private readonly NormalizeOrderInput $normalizer,
        private readonly EnsureBranchAcceptsOrders $branchGuard,
        private readonly NumberSequence $numbers,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function handle(Quote $quote, array $normalized, User $actor, int $actorMembershipId): Order
    {
        return DB::transaction(function () use ($quote, $normalized, $actor, $actorMembershipId): Order {
            $locked = Quote::query()
                ->where('tenant_id', $quote->tenant_id)
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->consumed_at !== null) {
                throw new ApiException('QUOTE_CONSUMED', 'The quote has already been consumed.', 409);
            }

            if ($locked->expires_at->isPast()) {
                throw new ApiException('QUOTE_EXPIRED', 'The quote has expired.', 422);
            }

            if ($locked->request_hash !== $this->normalizer->requestHash($normalized)) {
                throw new ApiException('QUOTE_PAYLOAD_MISMATCH', 'The request does not match the quote payload.', 422);
            }

            $branch = Branch::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->sales_branch_id)
                ->firstOrFail();
            $this->branchGuard->handle($branch);

            $snapshot = $locked->pricing_snapshot;
            $serviceIds = collect($snapshot['lines'])->pluck('service_id')->unique()->values()->all();
            $services = Service::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('public_id', $serviceIds)
                ->get()
                ->keyBy('public_id');

            if ($services->count() !== count($serviceIds)) {
                throw new ApiException('QUOTE_SERVICE_UNAVAILABLE', 'A service in the quote is no longer available.', 409);
            }

            $acceptedAt = now();
            $localDate = $acceptedAt->copy()->setTimezone($branch->timezone)->toDateString();
            $orderSequence = $this->numbers->next($locked->tenant_id, $branch->id, 'order', $localDate);
            $invoiceSequence = $this->numbers->next($locked->tenant_id, $branch->id, 'invoice', $localDate);

            $order = new Order([
                'quote_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'sales_branch_id' => $locked->sales_branch_id,
                'pickup_branch_id' => $locked->pickup_branch_id,
                'accepted_by_membership_id' => $actorMembershipId,
                'order_number' => $this->numbers->orderNumber($branch->code, $localDate, $orderSequence),
                'operational_status' => OrderStatus::Accepted,
                'fulfillment_method' => FulfillmentMethod::from($locked->fulfillment_method->value),
                'due_at_original' => $locked->due_at,
                'due_at_current' => $locked->due_at,
                'customer_snapshot' => $snapshot['customer_snapshot'],
                'accepted_at' => $acceptedAt,
            ]);
            $order->tenant_id = $locked->tenant_id;
            $order->save();

            $orderLines = [];
            foreach ($snapshot['lines'] as $line) {
                $orderLine = new OrderLine([
                    'order_id' => $order->id,
                    'service_id' => $services[$line['service_id']]->id,
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
                        'due_at' => $line['due_at'],
                    ],
                    'status' => 'active',
                ]);
                $orderLine->tenant_id = $locked->tenant_id;
                $orderLine->save();
                $orderLines[] = $orderLine;
            }

            $invoice = new Invoice([
                'order_id' => $order->id,
                'customer_id' => $locked->customer_id,
                'sales_branch_id' => $locked->sales_branch_id,
                'invoice_number' => $this->numbers->invoiceNumber($branch->code, $localDate, $invoiceSequence),
                'document_status' => InvoiceDocumentStatus::Issued,
                'currency' => 'IDR',
                'original_total_rupiah' => $locked->total_rupiah,
                'issued_at' => $acceptedAt,
                'due_at' => $locked->due_at,
            ]);
            $invoice->tenant_id = $locked->tenant_id;
            $invoice->save();

            $this->createInvoiceLines($invoice, $orderLines, $snapshot['totals'], $snapshot['lines']);

            $bag = new Bag([
                'order_id' => $order->id,
                'label_code' => 'BAG-'.mb_strtoupper((string) Str::ulid()),
                'current_location_id' => null,
                'custody_status' => 'at_location',
                'active_transfer_line_id' => null,
                'seal_code' => null,
            ]);
            $bag->tenant_id = $locked->tenant_id;
            $bag->save();

            foreach ($orderLines as $orderLine) {
                $content = new BagContent([
                    'order_id' => $order->id,
                    'bag_id' => $bag->id,
                    'order_line_id' => $orderLine->id,
                    'physical_quantity' => $orderLine->measured_quantity,
                    'handed_over_quantity' => '0.000',
                    'is_active' => true,
                ]);
                $content->tenant_id = $locked->tenant_id;
                $content->save();
            }

            $event = new OrderEvent([
                'order_id' => $order->id,
                'event_type' => 'accepted',
                'actor_membership_id' => $actorMembershipId,
                'reason' => null,
                'payload' => [
                    'quote_id' => $locked->public_id,
                    'order_number' => $order->order_number,
                    'total_rupiah' => $locked->total_rupiah,
                    'due_at' => $locked->due_at->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::OrderAccepted,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $order->public_id,
                changes: [
                    'order_number' => $order->order_number,
                    'quote_id' => $locked->public_id,
                    'total_rupiah' => $locked->total_rupiah,
                    'due_at' => $locked->due_at->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
                actor: $actor,
            );

            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'order.accepted',
                aggregatePublicId: $order->public_id,
                payload: [
                    'order_id' => $order->public_id,
                    'order_number' => $order->order_number,
                    'branch_id' => $branch->public_id,
                    'total_rupiah' => $locked->total_rupiah,
                    'due_at' => $locked->due_at->utc()->format('Y-m-d\TH:i:s\Z'),
                    'status' => OrderStatus::Accepted->value,
                ],
            );

            $locked->forceFill([
                'consumed_at' => $acceptedAt,
                'version' => $locked->version + 1,
            ])->save();

            return $order->refresh()->load(['lines', 'invoice.lines', 'bags.contents', 'events']);
        });
    }

    /**
     * @param  list<OrderLine>  $orderLines
     * @param  array<string, int>  $totals
     * @param  list<array<string, mixed>>  $snapshotLines
     */
    private function createInvoiceLines(
        Invoice $invoice,
        array $orderLines,
        array $totals,
        array $snapshotLines,
    ): void {
        $sum = 0;

        foreach ($orderLines as $index => $orderLine) {
            $line = $snapshotLines[$index];
            $amount = $line['base_total_rupiah'];
            $sum += $amount;
            $invoiceLine = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'order_line_id' => $orderLine->id,
                'kind' => 'service',
                'description' => $orderLine->service_snapshot['name'],
                'unit' => $orderLine->unit->value,
                'quantity' => $orderLine->billable_quantity,
                'unit_price_rupiah' => $orderLine->unit_price_rupiah,
                'amount_rupiah' => $amount,
            ]);
            $invoiceLine->tenant_id = $invoice->tenant_id;
            $invoiceLine->save();
        }

        if ($totals['discount_rupiah'] > 0) {
            $sum -= $totals['discount_rupiah'];
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'order_line_id' => null,
                'kind' => 'discount',
                'description' => 'Service discount',
                'amount_rupiah' => -$totals['discount_rupiah'],
            ]);
            $line->tenant_id = $invoice->tenant_id;
            $line->save();
        }

        if ($totals['express_surcharge_rupiah'] > 0) {
            $sum += $totals['express_surcharge_rupiah'];
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'order_line_id' => null,
                'kind' => 'service',
                'description' => 'Express surcharge',
                'unit' => 'surcharge',
                'quantity' => '1.000',
                'unit_price_rupiah' => $totals['express_surcharge_rupiah'],
                'amount_rupiah' => $totals['express_surcharge_rupiah'],
            ]);
            $line->tenant_id = $invoice->tenant_id;
            $line->save();
        }

        if ($totals['delivery_fee_rupiah'] > 0) {
            $sum += $totals['delivery_fee_rupiah'];
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'order_line_id' => null,
                'kind' => 'delivery',
                'description' => 'Delivery',
                'amount_rupiah' => $totals['delivery_fee_rupiah'],
            ]);
            $line->tenant_id = $invoice->tenant_id;
            $line->save();
        }

        if ($sum !== $invoice->original_total_rupiah) {
            throw new ApiException('QUOTE_TOTAL_MISMATCH', 'The invoice total does not match the accepted quote.', 409);
        }
    }
}
