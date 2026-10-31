<?php

namespace App\Modules\Order\Actions;

use App\Modules\Catalog\Actions\CaptureServiceSnapshot;
use App\Modules\Catalog\Actions\ResolvePrice;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\Service;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Actions\CalculateDeadline;
use App\Modules\Operations\Actions\EnsureBranchAcceptsOrders;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class BuildQuote
{
    public function __construct(
        private readonly NormalizeOrderInput $normalizer,
        private readonly ResolvePrice $prices,
        private readonly CalculateDeadline $deadlines,
        private readonly CaptureServiceSnapshot $snapshots,
        private readonly EnsureBranchAcceptsOrders $branchGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function handle(int $tenantId, array $normalized, ?CarbonImmutable $now = null): array
    {
        $quotedAt = ($now ?? CarbonImmutable::now())->utc();
        $branch = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $normalized['branch_id'])
            ->first();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch must belong to this tenant.'],
            ]);
        }

        $this->branchGuard->handle($branch);

        $pickupBranch = null;
        if ($normalized['pickup_branch_id'] !== null) {
            $pickupBranch = Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('public_id', $normalized['pickup_branch_id'])
                ->first();

            if ($pickupBranch === null) {
                throw ValidationException::withMessages([
                    'pickup_branch_id' => ['The selected pickup branch must belong to this tenant.'],
                ]);
            }
        }

        $customer = $this->customer($tenantId, $normalized['customer_id']);

        $servicePublicIds = array_column($normalized['lines'], 'service_id');
        $services = Service::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('public_id', $servicePublicIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('public_id');

        if ($services->count() !== count(array_unique($servicePublicIds))) {
            throw ValidationException::withMessages([
                'lines' => ['Every line must reference an active service in this tenant.'],
            ]);
        }

        $quotedAtIso = $quotedAt->format('Y-m-d\TH:i:s\Z');
        $lines = [];
        $dueCandidates = [];

        foreach ($normalized['lines'] as $index => $line) {
            $service = $services->get($line['service_id']);
            $tier = PriceTier::from($line['tier']);

            if (! $service->unit->accepts($line['measured_quantity'])) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.measured_quantity' => ['The measured quantity must be a whole number for this unit.'],
                ]);
            }

            $pricing = $this->prices->handle(
                $tenantId,
                $branch->public_id,
                $service->public_id,
                $tier,
                $quotedAtIso,
            );
            $deadline = $this->deadlines->handle($branch, $service, $tier, $quotedAtIso);
            $billableMilli = $this->billableMilli($line['measured_quantity'], $service);
            $baseTotal = $this->money($pricing['unit_price_rupiah'], $billableMilli);

            $lines[] = [
                'service_id' => $service->public_id,
                'service_code' => $service->code,
                'service_name' => $service->name,
                'unit' => $service->unit->value,
                'tier' => $tier->value,
                'measured_quantity' => $line['measured_quantity'],
                'billable_quantity' => $this->quantity($billableMilli),
                'unit_price_rupiah' => $pricing['unit_price_rupiah'],
                'base_total_rupiah' => $baseTotal,
                'discount_rupiah' => 0,
                'express_surcharge_rupiah' => 0,
                'line_total_rupiah' => $baseTotal,
                'pricing' => $pricing,
                'service_snapshot' => $this->snapshots->handle($service),
                'due_at' => $deadline['due_at'],
            ];
            $dueCandidates[] = $deadline['due_at'];
        }

        $serviceSubtotal = array_sum(array_column($lines, 'base_total_rupiah'));
        $discount = $this->percentage($serviceSubtotal, $normalized['service_discount_percent']);
        $remainingDiscount = $discount;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => &$line) {
            $lineDiscount = $index === $lastIndex
                ? $remainingDiscount
                : ($serviceSubtotal === 0 ? 0 : intdiv($discount * $line['base_total_rupiah'], $serviceSubtotal));

            $remainingDiscount -= $lineDiscount;
            $discounted = $line['base_total_rupiah'] - $lineDiscount;
            $surcharge = $line['tier'] === PriceTier::Express->value
                ? $this->percentage($discounted, (int) config('pricing.express_surcharge_percent', 50))
                : 0;

            $line['discount_rupiah'] = $lineDiscount;
            $line['express_surcharge_rupiah'] = $surcharge;
            $line['line_total_rupiah'] = $discounted + $surcharge;
        }
        unset($line);

        $expressSurcharge = array_sum(array_column($lines, 'express_surcharge_rupiah'));
        $deliveryFee = $normalized['fulfillment_method'] === 'delivery'
            ? (int) config('pricing.delivery_fee_rupiah', 5000)
            : 0;
        $total = $serviceSubtotal - $discount + $expressSurcharge + $deliveryFee;
        $dueAt = CarbonImmutable::parse(max($dueCandidates))->utc();
        $expiresAt = $quotedAt->addMinutes((int) config('pricing.quote_ttl_minutes', 15));
        $customerSnapshot = $this->customerSnapshot($customer);

        return [
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $pickupBranch?->id,
            'customer_id' => $customer?->id,
            'fulfillment_method' => $normalized['fulfillment_method'],
            'instructions' => $normalized['instructions'],
            'expires_at' => $expiresAt,
            'due_at' => $dueAt,
            'request_hash' => $this->normalizer->requestHash($normalized),
            'total_rupiah' => $total,
            'customer_snapshot' => $customerSnapshot,
            'pricing_snapshot' => [
                'input' => $normalized,
                'priced_at' => $quotedAtIso,
                'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
                'customer_snapshot' => $customerSnapshot,
                'lines' => $lines,
                'totals' => [
                    'service_subtotal_rupiah' => $serviceSubtotal,
                    'discount_rupiah' => $discount,
                    'express_surcharge_rupiah' => $expressSurcharge,
                    'delivery_fee_rupiah' => $deliveryFee,
                    'total_rupiah' => $total,
                ],
            ],
        ];
    }

    private function customer(int $tenantId, ?string $publicId): ?Customer
    {
        if ($publicId === null) {
            return null;
        }

        $customer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => ['The selected customer must be active and belong to this tenant.'],
            ]);
        }

        return $customer->load('addresses');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSnapshot(?Customer $customer): array
    {
        if ($customer === null) {
            return ['name' => 'Walk-in'];
        }

        $address = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();

        return [
            'id' => $customer->public_id,
            'name' => $customer->name,
            'normalized_phone' => $customer->normalized_phone,
            'email' => $customer->email,
            'address_label' => $address?->label,
            'address' => $address?->address,
        ];
    }

    private function billableMilli(string $measured, Service $service): int
    {
        $measuredMilli = $this->milli($measured);
        $minimumMilli = $this->milli((string) $service->minimum_quantity);
        $stepMilli = $this->milli((string) $service->quantity_step);
        $rounded = intdiv($measuredMilli + $stepMilli - 1, $stepMilli) * $stepMilli;

        return max($minimumMilli, $rounded);
    }

    private function milli(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $fraction;
    }

    private function quantity(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function money(int $unitPrice, int $quantityMilli): int
    {
        return intdiv(($unitPrice * $quantityMilli) + 500, 1000);
    }

    private function percentage(int $amount, int $percent): int
    {
        return intdiv(($amount * $percent) + 50, 100);
    }
}
