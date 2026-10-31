<?php

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\BagRepackEvent;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\QuantityMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RepackBag
{
    public function __construct(
        private readonly QuantityMath $math,
        private readonly AuditRecorder $auditor,
    ) {}

    /**
     * @param  array{source_bag_id: string, target_bag_id?: string|null, reason: string, moves: list<array{order_line_id: string, quantity: string}>}  $attributes
     * @return array{source: Bag, target: Bag}
     */
    public function handle(Order $order, array $attributes, int $membershipId, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $attributes, $membershipId, $actor): array {
            $lockedOrder = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->operational_status, [OrderStatus::Accepted, OrderStatus::InProgress, OrderStatus::PartiallyReady, OrderStatus::Ready], true)) {
                throw ValidationException::withMessages([
                    'order' => ['The order cannot be repacked in its current status.'],
                ]);
            }

            $source = Bag::query()
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('order_id', $lockedOrder->id)
                ->where('public_id', $attributes['source_bag_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $target = $this->targetBag($lockedOrder, $source, $attributes['target_bag_id'] ?? null);
            $target->load('contents');

            $moves = collect($attributes['moves'])->keyBy('order_line_id');
            $sourceContents = BagContent::query()
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->where('order_id', $lockedOrder->id)
                ->where('bag_id', $source->id)
                ->with('orderLine')
                ->whereIn('order_line_id', function ($query) use ($lockedOrder, $moves): void {
                    $query->select('id')
                        ->from('order_lines')
                        ->where('tenant_id', $lockedOrder->tenant_id)
                        ->where('order_id', $lockedOrder->id)
                        ->whereIn('public_id', $moves->keys());
                })
                ->lockForUpdate()
                ->get();
            $moved = [];

            foreach ($moves as $linePublicId => $move) {
                $sourceContent = $sourceContents->first(fn (BagContent $content): bool => $content->orderLine?->public_id === $linePublicId);
                if ($sourceContent === null) {
                    throw ValidationException::withMessages([
                        'moves' => ['A moved line is not active in the source bag.'],
                    ]);
                }

                $availableMilli = $this->math->milli((string) $sourceContent->physical_quantity)
                    - $this->math->milli((string) $sourceContent->handed_over_quantity);
                $moveMilli = $this->math->milli((string) $move['quantity']);

                if ($moveMilli <= 0 || $moveMilli > $availableMilli) {
                    throw ValidationException::withMessages([
                        'moves' => ['The moved quantity exceeds the remaining source quantity.'],
                    ]);
                }

                $remainingMilli = $availableMilli - $moveMilli;
                $sourceContent->forceFill([
                    'physical_quantity' => $this->math->quantity($remainingMilli),
                    'is_active' => $remainingMilli > 0,
                    'version' => $sourceContent->version + 1,
                ])->save();

                $targetContent = BagContent::query()
                    ->where('tenant_id', $lockedOrder->tenant_id)
                    ->where('order_id', $lockedOrder->id)
                    ->where('bag_id', $target->id)
                    ->where('order_line_id', $sourceContent->order_line_id)
                    ->first();

                if ($targetContent === null) {
                    $targetContent = new BagContent([
                        'order_id' => $lockedOrder->id,
                        'bag_id' => $target->id,
                        'order_line_id' => $sourceContent->order_line_id,
                        'physical_quantity' => $this->math->quantity($moveMilli),
                        'handed_over_quantity' => '0.000',
                        'is_active' => true,
                    ]);
                    $targetContent->tenant_id = $lockedOrder->tenant_id;
                    $targetContent->save();
                } else {
                    $targetContent->forceFill([
                        'physical_quantity' => $this->math->quantity($this->math->milli((string) $targetContent->physical_quantity) + $moveMilli),
                        'is_active' => true,
                        'version' => $targetContent->version + 1,
                    ])->save();
                }

                $moved[] = [
                    'order_line_id' => $linePublicId,
                    'quantity' => $this->math->quantity($moveMilli),
                ];
            }

            $event = new BagRepackEvent([
                'order_id' => $lockedOrder->id,
                'source_bag_id' => $source->id,
                'target_bag_id' => $target->id,
                'actor_membership_id' => $membershipId,
                'allocation_snapshot' => [
                    'source_bag_id' => $source->public_id,
                    'target_bag_id' => $target->public_id,
                    'moves' => $moved,
                ],
                'reason' => $attributes['reason'],
            ]);
            $event->tenant_id = $lockedOrder->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $lockedOrder->tenant_id,
                action: AuditAction::BagRepacked,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $lockedOrder->public_id,
                changes: [
                    'source_bag_id' => $source->public_id,
                    'target_bag_id' => $target->public_id,
                    'moves' => $moved,
                    'reason' => $attributes['reason'],
                ],
                actor: $actor,
                reason: $attributes['reason'],
            );

            return [
                'source' => $source->refresh()->load('contents'),
                'target' => $target->refresh()->load('contents'),
            ];
        });
    }

    private function targetBag(Order $order, Bag $source, ?string $publicId): Bag
    {
        if ($publicId !== null) {
            $target = Bag::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_id', $order->id)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target->id === $source->id) {
                throw ValidationException::withMessages([
                    'target_bag_id' => ['Source and target bag must be different.'],
                ]);
            }

            return $target;
        }

        $bag = new Bag([
            'order_id' => $order->id,
            'label_code' => 'BAG-'.mb_strtoupper((string) Str::ulid()),
            'current_location_id' => null,
            'custody_status' => 'at_location',
            'active_transfer_line_id' => null,
            'seal_code' => null,
        ]);
        $bag->tenant_id = $order->tenant_id;
        $bag->save();

        return $bag;
    }
}
