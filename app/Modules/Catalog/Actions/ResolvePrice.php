<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\EffectivePeriod;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class ResolvePrice
{
    public function __construct(private readonly EffectivePeriod $periods) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        int $tenantId,
        string $branchPublicId,
        string $servicePublicId,
        PriceTier $tier = PriceTier::Regular,
        ?string $at = null,
    ): array {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $branch = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $branchPublicId)
            ->first();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch must belong to this tenant.'],
            ]);
        }

        $service = Service::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $servicePublicId)
            ->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'service_id' => ['The selected service must belong to this tenant.'],
            ]);
        }

        $resolvedAt = $this->resolveAt($at, $tenant->timezone);

        $books = PriceBook::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('customer_id')
            ->where(function ($query) use ($branch): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->with('branch')
            ->orderByRaw('branch_id IS NULL')
            ->orderByDesc('id')
            ->get();

        foreach ($books as $book) {
            $entry = PriceEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('price_book_id', $book->id)
                ->where('service_id', $service->id)
                ->where('tier', $tier->value)
                ->where('effective_from', '<=', $resolvedAt)
                ->where(function ($query) use ($resolvedAt): void {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>', $resolvedAt);
                })
                ->orderByDesc('effective_from')
                ->first();

            if ($entry === null) {
                continue;
            }

            return [
                'service_id' => $service->public_id,
                'service_code' => $service->code,
                'service_name' => $service->name,
                'tier' => $tier->value,
                'unit_price_rupiah' => $entry->unit_price_rupiah,
                'source' => $book->branch_id === null ? 'tenant' : 'branch',
                'branch_id' => $branch->public_id,
                'price_book' => [
                    'id' => $book->public_id,
                    'name' => $book->name,
                    'scope' => $book->branch_id === null ? 'tenant' : 'branch',
                    'branch_id' => $book->branch?->public_id,
                ],
                'price_entry_id' => $entry->public_id,
                'effective_from' => $entry->effective_from->utc()->format('Y-m-d\TH:i:s\Z'),
                'effective_until' => $entry->effective_until?->utc()->format('Y-m-d\TH:i:s\Z'),
                'effective_from_date' => $this->periods->localDate($entry->effective_from, $tenant->timezone),
                'effective_to_date' => $entry->effective_until === null
                    ? null
                    : $this->periods->localEndDate($entry->effective_until, $tenant->timezone),
                'resolved_at' => $resolvedAt->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        throw ValidationException::withMessages([
            'service_id' => ['No effective price is configured for this service, tier, branch, and time.'],
        ]);
    }

    private function resolveAt(?string $at, string $timezone): CarbonImmutable
    {
        if ($at === null) {
            return CarbonImmutable::now()->utc();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $at) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $at, new DateTimeZone($timezone))->startOfDay()->utc();
        }

        return CarbonImmutable::parse($at)->utc();
    }
}
