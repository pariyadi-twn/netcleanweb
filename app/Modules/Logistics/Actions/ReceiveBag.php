<?php

namespace App\Modules\Logistics\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Location;
use App\Modules\Logistics\Models\CustodyEvent;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\Order;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceiveBag
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  array{location_id: string, expected_version: int, reason?: ?string}  $attributes
     * @return array{bag: Bag, replay: bool}
     */
    public function handle(Order $order, string $bagPublicId, array $attributes, User $actor): array
    {
        return DB::transaction(function () use ($order, $bagPublicId, $attributes, $actor): array {
            $lockedOrder = Order::query()->where('tenant_id', $order->tenant_id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedOrder->operational_status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
                throw new ApiException('ORDER_NOT_RECEIVABLE', 'The order cannot receive bags at this stage.', 409);
            }

            $bag = $lockedOrder->bags()->where('public_id', $bagPublicId)->lockForUpdate()->firstOrFail();
            $location = Location::query()->where('tenant_id', $order->tenant_id)->where('public_id', $attributes['location_id'])->firstOrFail();
            if (! $this->context->canAccessLocation($location)) {
                throw new AuthorizationException('This action is unauthorized.');
            }
            if (! $location->is_active || $location->branch_id !== $lockedOrder->sales_branch_id) {
                throw ValidationException::withMessages(['location_id' => ['The receiving location must be active and belong to the sales branch.']]);
            }
            if ($bag->custody_status !== 'at_location' || $bag->active_transfer_line_id !== null) {
                throw new ApiException('BAG_NOT_RECEIVABLE', 'The bag is handed over or involved in a transfer.', 409);
            }
            if ($bag->current_location_id !== null) {
                $received = CustodyEvent::query()->where('tenant_id', $bag->tenant_id)->where('bag_id', $bag->id)
                    ->where('event_type', 'received')->where('to_location_id', $location->id)->exists();
                if ($bag->current_location_id === $location->id && $received) {
                    return ['bag' => $bag, 'replay' => true];
                }
                throw new ApiException('BAG_ALREADY_LOCATED', 'A located bag must use the custody transfer workflow.', 409);
            }
            if ($bag->version !== (int) $attributes['expected_version']) {
                throw new ApiException('BAG_VERSION_CONFLICT', 'The bag has changed. Refresh before receiving it.', 409);
            }
            if (! $bag->contents()->where('is_active', true)->whereColumn('physical_quantity', '>', 'handed_over_quantity')->exists()) {
                throw new ApiException('BAG_EMPTY', 'The bag has no remaining active content.', 409);
            }

            $bag->forceFill(['current_location_id' => $location->id, 'version' => $bag->version + 1])->save();
            $event = new CustodyEvent([
                'bag_id' => $bag->id, 'event_type' => 'received', 'from_location_id' => null,
                'to_location_id' => $location->id, 'actor_membership_id' => $this->context->membershipId,
            ]);
            $event->tenant_id = $bag->tenant_id;
            $event->save();
            $changes = ['bag_id' => $bag->public_id, 'location_id' => $location->public_id, 'custody_event_id' => $event->public_id];
            $this->auditor->record(
                tenantId: $bag->tenant_id, action: AuditAction::BagReceived, subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $order->public_id, changes: $changes, actor: $actor, reason: $attributes['reason'] ?? null,
            );
            $this->outbox->record($bag->tenant_id, 'bag.received', $bag->public_id, ['order_id' => $order->public_id, ...$changes]);

            return ['bag' => $bag->refresh(), 'replay' => false];
        });
    }
}
