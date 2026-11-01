<?php

namespace App\Modules\Logistics\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Location;
use App\Modules\Logistics\Models\CustodyEvent;
use App\Modules\Logistics\Models\Handover;
use App\Modules\Logistics\Models\HandoverLine;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\CreditApproval;
use App\Modules\Production\Actions\AssessOrderReadiness;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateHandover
{
    public function __construct(
        private readonly AssessOrderReadiness $readiness,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array{bag_ids: array<int, string>, location_id: string, recipient_name: string, verification_method: string, credit_approval_id?: string|null}  $attributes
     * @return array{handover: Handover, order: Order}
     */
    public function handle(Order $order, array $attributes, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $attributes, $membershipId, $actor): array {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->operational_status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
                throw new ApiException('ORDER_NOT_HANDOVERABLE', 'The order cannot be handed over at this stage.', 409);
            }

            $location = Location::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('public_id', $attributes['location_id'])
                ->firstOrFail();
            if (! $this->context->canAccessLocation($location)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $bags = $locked->bags()
                ->whereIn('public_id', array_values(array_unique($attributes['bag_ids'])))
                ->with(['contents.orderLine'])
                ->lockForUpdate()
                ->get();
            if ($bags->count() !== count(array_unique($attributes['bag_ids']))) {
                throw ValidationException::withMessages(['bag_ids' => ['Every bag must belong to this order.']]);
            }
            foreach ($bags as $bag) {
                if ($bag->custody_status === 'handed_over' || $bag->active_transfer_line_id !== null || $bag->current_location_id !== $location->id) {
                    throw new ApiException('BAG_NOT_HANDOVERABLE', 'A bag is not at the handover location or is already handed over.', 409);
                }
            }

            $readiness = $this->readiness->snapshot($locked);
            $readyContents = collect($readiness['contents'])->keyBy('bag_content_id');
            foreach ($bags as $bag) {
                foreach ($bag->contents->where('is_active', true) as $content) {
                    if (($readyContents->get($content->public_id)['ready'] ?? false) !== true) {
                        throw new ApiException('ORDER_CONTENT_NOT_READY', 'Every bag content must pass production QC before handover.', 409);
                    }
                }
            }

            $invoice = $locked->invoice;
            $balance = $invoice === null ? 0 : $invoice->totalRupiah() - $invoice->netAllocatedRupiah();
            $creditApproval = null;
            if ($balance > 0) {
                $creditApproval = CreditApproval::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('public_id', $attributes['credit_approval_id'] ?? null)
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->first();
                if ($creditApproval === null || $creditApproval->limit_rupiah < $balance) {
                    throw new ApiException('PAYMENT_OR_CREDIT_REQUIRED', 'Outstanding balance requires full payment or an active credit approval.', 409);
                }
            }

            $handover = new Handover([
                'order_id' => $locked->id,
                'location_id' => $location->id,
                'actor_membership_id' => $membershipId,
                'recipient_name' => $attributes['recipient_name'],
                'verification_method' => $attributes['verification_method'],
                'credit_approval_id' => $creditApproval?->id,
                'completed_at' => now(),
            ]);
            $handover->tenant_id = $locked->tenant_id;
            $handover->save();

            foreach ($bags as $bag) {
                foreach ($bag->contents->where('is_active', true) as $content) {
                    $remaining = (float) $content->physical_quantity - (float) $content->handed_over_quantity;
                    if ($remaining <= 0) {
                        continue;
                    }

                    $line = new HandoverLine([
                        'handover_id' => $handover->id,
                        'order_id' => $locked->id,
                        'bag_content_id' => $content->id,
                        'quantity' => number_format($remaining, 3, '.', ''),
                    ]);
                    $line->tenant_id = $locked->tenant_id;
                    $line->save();

                    $content->forceFill([
                        'handed_over_quantity' => number_format((float) $content->handed_over_quantity + $remaining, 3, '.', ''),
                        'version' => $content->version + 1,
                    ])->save();

                    $event = new CustodyEvent([
                        'bag_id' => $bag->id,
                        'event_type' => 'handed_over',
                        'from_location_id' => $location->id,
                        'to_location_id' => null,
                        'handover_line_id' => $line->id,
                        'actor_membership_id' => $membershipId,
                    ]);
                    $event->tenant_id = $locked->tenant_id;
                    $event->save();
                }

                $bag->forceFill([
                    'custody_status' => 'handed_over',
                    'current_location_id' => null,
                    'version' => $bag->version + 1,
                ])->save();
            }

            $remaining = DB::table('bag_contents')
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $locked->id)
                ->where('is_active', true)
                ->whereColumn('handed_over_quantity', '<', 'physical_quantity')
                ->exists();
            $status = $remaining ? OrderStatus::PartiallyHandedOver : OrderStatus::Completed;
            $locked->forceFill([
                'operational_status' => $status,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::HandoverCompleted,
                subjectType: AuditRecorder::SUBJECT_HANDOVER,
                subjectPublicId: $handover->public_id,
                changes: [
                    'order_id' => $locked->public_id,
                    'bags' => $bags->pluck('public_id')->all(),
                    'balance_rupiah' => max(0, $balance),
                    'verification_method' => $handover->verification_method,
                ],
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'order.handed_over',
                aggregatePublicId: $locked->public_id,
                payload: [
                    'order_id' => $locked->public_id,
                    'handover_id' => $handover->public_id,
                    'status' => $status->value,
                ],
            );

            return ['handover' => $handover->refresh()->load(['lines', 'location']), 'order' => $locked->refresh()];
        });
    }
}
