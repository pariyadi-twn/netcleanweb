<?php

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Catalog\Actions\CaptureServiceSnapshot;
use App\Modules\Catalog\Actions\ResolvePrice;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Enums\OrderRevisionStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderRevision;
use App\Modules\Order\Support\QuantityMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateOrderRevision
{
    public function __construct(
        private readonly ResolvePrice $prices,
        private readonly CaptureServiceSnapshot $snapshots,
        private readonly QuantityMath $math,
        private readonly ApplyOrderRevision $apply,
    ) {}

    /**
     * @param  array{expected_version: int, reason: string, lines: list<array<string, mixed>>}  $attributes
     */
    public function handle(Order $order, array $attributes, int $actorMembershipId, ?User $actor = null): OrderRevision
    {
        return DB::transaction(function () use ($order, $attributes, $actorMembershipId, $actor): OrderRevision {
            $locked = Order::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($order->id)
                ->with(['lines.service', 'quote'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureRevisionAllowed($locked, (int) $attributes['expected_version']);

            $invoice = Invoice::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $locked->id)
                ->with('adjustments')
                ->firstOrFail();
            $currentTotal = (int) $invoice->original_total_rupiah + (int) $invoice->adjustments->sum('amount_rupiah');
            $changes = collect($attributes['lines'])->keyBy('order_line_id');
            $branch = Branch::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->sales_branch_id)
                ->firstOrFail();
            $now = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s\Z');
            $newServices = Service::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('public_id', $changes->pluck('service_id')->filter()->unique()->values())
                ->where('is_active', true)
                ->get()
                ->keyBy('public_id');

            $lines = [];
            $oldLines = [];
            foreach ($locked->lines as $line) {
                $change = $changes->get($line->public_id, []);
                $oldLines[] = [
                    'id' => $line->public_id,
                    'service_id' => $line->service_snapshot['id'] ?? null,
                    'measured_quantity' => $line->measured_quantity,
                    'billable_quantity' => $line->billable_quantity,
                    'unit_price_rupiah' => $line->unit_price_rupiah,
                    'line_total_rupiah' => $line->line_total_rupiah,
                ];

                $service = $line->service;
                $serviceChanged = isset($change['service_id']) && $change['service_id'] !== $service->public_id;
                if ($serviceChanged) {
                    $service = $newServices->get($change['service_id']);
                    if ($service === null) {
                        throw ValidationException::withMessages([
                            'lines' => ['The replacement service must be active and belong to this tenant.'],
                        ]);
                    }
                }

                $measured = (string) ($change['measured_quantity'] ?? $line->measured_quantity);
                if (! $service->unit->accepts($measured)) {
                    throw ValidationException::withMessages([
                        'lines' => ['The measured quantity is invalid for the selected service.'],
                    ]);
                }

                $tier = PriceTier::tryFrom((string) ($line->service_snapshot['pricing']['tier'] ?? PriceTier::Regular->value))
                    ?? PriceTier::Regular;
                $reprice = (bool) ($change['reprice'] ?? false) || $serviceChanged;
                $unitPrice = $line->unit_price_rupiah;
                $serviceSnapshot = $line->service_snapshot;
                $pricing = $line->service_snapshot['pricing'] ?? null;

                if ($reprice) {
                    $pricing = $this->prices->handle(
                        $locked->tenant_id,
                        $branch->public_id,
                        $service->public_id,
                        $tier,
                        $now,
                    );
                    $unitPrice = $pricing['unit_price_rupiah'];
                    $serviceSnapshot = $this->snapshots->handle($service);
                }

                $billableMilli = $serviceChanged || $measured !== $line->measured_quantity
                    ? $this->math->billable($measured, (string) $service->minimum_quantity, (string) $service->quantity_step)
                    : $this->math->milli((string) $line->billable_quantity);
                $baseTotal = $serviceChanged || $reprice || $measured !== $line->measured_quantity
                    ? $this->math->money($unitPrice, $billableMilli)
                    : (int) ($line->service_snapshot['base_total_rupiah'] ?? $line->line_total_rupiah);

                $lines[] = [
                    'id' => $line->public_id,
                    'service_id' => $service->public_id,
                    'service_name' => $service->name,
                    'unit' => $service->unit->value,
                    'tier' => $tier->value,
                    'measured_quantity' => $measured,
                    'billable_quantity' => $this->math->quantity($billableMilli),
                    'unit_price_rupiah' => $unitPrice,
                    'base_total_rupiah' => $baseTotal,
                    'discount_rupiah' => 0,
                    'express_surcharge_rupiah' => 0,
                    'line_total_rupiah' => $baseTotal,
                    'pricing' => $pricing,
                    'service_snapshot' => $serviceSnapshot,
                    'order_line_id' => $line->id,
                ];
            }

            $quoteSnapshot = $locked->quote->pricing_snapshot;
            $discountPercent = (int) ($quoteSnapshot['input']['service_discount_percent'] ?? 0);
            $serviceSubtotal = array_sum(array_column($lines, 'base_total_rupiah'));
            $discount = $this->math->percentage($serviceSubtotal, $discountPercent);
            $remainingDiscount = $discount;
            $lastIndex = count($lines) - 1;

            foreach ($lines as $index => &$line) {
                $lineDiscount = $index === $lastIndex
                    ? $remainingDiscount
                    : ($serviceSubtotal === 0 ? 0 : intdiv($discount * $line['base_total_rupiah'], $serviceSubtotal));
                $remainingDiscount -= $lineDiscount;
                $discounted = $line['base_total_rupiah'] - $lineDiscount;
                $surcharge = $line['tier'] === PriceTier::Express->value
                    ? $this->math->percentage($discounted, (int) config('pricing.express_surcharge_percent', 50))
                    : 0;
                $line['discount_rupiah'] = $lineDiscount;
                $line['express_surcharge_rupiah'] = $surcharge;
                $line['line_total_rupiah'] = $discounted + $surcharge;
                unset($line['order_line_id']);
            }
            unset($line);

            $delivery = (int) ($quoteSnapshot['totals']['delivery_fee_rupiah'] ?? 0);
            $newTotal = $serviceSubtotal - $discount
                + array_sum(array_column($lines, 'express_surcharge_rupiah')) + $delivery;
            $difference = $newTotal - $currentTotal;

            if ($difference === 0) {
                throw ValidationException::withMessages([
                    'lines' => ['The revision must change the order total.'],
                ]);
            }

            $requiresApproval = abs($difference) > 100000
                || abs($difference) > $this->math->percentage($currentTotal, 10);
            $revision = new OrderRevision([
                'order_id' => $locked->id,
                'status' => OrderRevisionStatus::Pending,
                'expected_version' => $locked->version,
                'reason' => $attributes['reason'],
                'requested_by_membership_id' => $actorMembershipId,
                'payload' => [
                    'old_lines' => $oldLines,
                    'new_lines' => $lines,
                    'old_total_rupiah' => $currentTotal,
                    'new_total_rupiah' => $newTotal,
                    'delivery_fee_rupiah' => $delivery,
                ],
                'difference_rupiah' => $difference,
                'current_total_rupiah' => $currentTotal,
            ]);
            $revision->tenant_id = $locked->tenant_id;
            $revision->save();

            if (! $requiresApproval) {
                $this->apply->handle($revision, $actorMembershipId, $actor, 'Auto-applied within threshold');
            }

            return $revision->refresh();
        });
    }

    private function ensureRevisionAllowed(Order $order, int $expectedVersion): void
    {
        if (! in_array($order->operational_status, [OrderStatus::Accepted, OrderStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'order' => ['Only accepted or in-progress orders can be revised.'],
            ]);
        }

        if ($order->version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'expected_version' => ['The order version is stale. Reload before revising.'],
            ]);
        }
    }
}
