<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\EffectivePeriod;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreatePriceEntry
{
    public function __construct(private readonly EffectivePeriod $periods) {}

    /**
     * @param  array{service_id: string, tier: string, unit_price_rupiah: int, effective_from: string, effective_to?: string|null, rules?: array<string, mixed>}  $attributes
     */
    public function handle(PriceBook $book, array $attributes): PriceEntry
    {
        if (! $book->is_active) {
            throw ValidationException::withMessages([
                'price_book_id' => ['Prices can only be added to an active price book.'],
            ]);
        }

        $service = $this->service($book->tenant_id, $attributes['service_id']);
        $tenant = Tenant::query()->findOrFail($book->tenant_id);
        $tier = PriceTier::from($attributes['tier']);
        [$effectiveFrom, $effectiveUntil] = $this->periods->fromDates(
            $tenant->timezone,
            $attributes['effective_from'],
            $attributes['effective_to'] ?? null,
        );

        return DB::transaction(function () use ($book, $service, $tier, $attributes, $effectiveFrom, $effectiveUntil): PriceEntry {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'catalog-price-entry:'.$book->tenant_id.':'.$book->id.':'.$service->id.':'.$tier->value,
            ]);

            $this->ensureNoOverlap($book, $service, $tier, $effectiveFrom, $effectiveUntil);

            $entry = new PriceEntry([
                'price_book_id' => $book->id,
                'service_id' => $service->id,
                'tier' => $tier,
                'unit_price_rupiah' => $attributes['unit_price_rupiah'],
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'rules' => $attributes['rules'] ?? [],
            ]);
            $entry->tenant_id = $book->tenant_id;

            try {
                $entry->save();
            } catch (QueryException $exception) {
                if ($exception->errorInfo[0] === '23P01') {
                    throw ValidationException::withMessages([
                        'effective_from' => ['The price period overlaps an existing entry for this service and tier.'],
                    ]);
                }

                throw $exception;
            }

            return $entry->load('service', 'priceBook.branch');
        });
    }

    private function service(int $tenantId, string $publicId): Service
    {
        $service = Service::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'service_id' => ['The selected service must belong to this tenant.'],
            ]);
        }

        return $service;
    }

    private function ensureNoOverlap(
        PriceBook $book,
        Service $service,
        PriceTier $tier,
        \DateTimeInterface $from,
        ?\DateTimeInterface $until,
    ): void {
        $overlap = PriceEntry::query()
            ->where('tenant_id', $book->tenant_id)
            ->where('price_book_id', $book->id)
            ->where('service_id', $service->id)
            ->where('tier', $tier->value)
            ->when($until !== null, fn ($query) => $query->where('effective_from', '<', $until))
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $from);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => ['The price period overlaps an existing entry for this service and tier.'],
            ]);
        }
    }
}
