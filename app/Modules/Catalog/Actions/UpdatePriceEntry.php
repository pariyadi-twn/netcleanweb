<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Support\EffectivePeriod;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePriceEntry
{
    public function __construct(private readonly EffectivePeriod $periods) {}

    /**
     * @param  array{effective_to: string}  $attributes
     */
    public function handle(PriceEntry $entry, array $attributes): PriceEntry
    {
        $tenant = Tenant::query()->findOrFail($entry->tenant_id);
        [, $effectiveUntil] = $this->periods->fromDates(
            $tenant->timezone,
            $entry->effective_from->setTimezone($tenant->timezone)->toDateString(),
            $attributes['effective_to'],
        );

        if ($effectiveUntil === null || $effectiveUntil <= $entry->effective_from) {
            throw ValidationException::withMessages([
                'effective_to' => ['The effective to date must be on or after the effective from date.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $effectiveUntil): PriceEntry {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'catalog-price-entry:'.$entry->tenant_id.':'.$entry->price_book_id.':'.$entry->service_id.':'.$entry->tier->value,
            ]);

            $locked = PriceEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $overlap = PriceEntry::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('price_book_id', $locked->price_book_id)
                ->where('service_id', $locked->service_id)
                ->where('tier', $locked->tier->value)
                ->whereKeyNot($locked->id)
                ->where('effective_from', '<', $effectiveUntil)
                ->where(function ($query) use ($locked): void {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>', $locked->effective_from);
                })
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'effective_to' => ['The price period overlaps an existing entry for this service and tier.'],
                ]);
            }

            try {
                $locked->forceFill([
                    'effective_until' => $effectiveUntil,
                    'version' => $locked->version + 1,
                ])->save();
            } catch (QueryException $exception) {
                if ($exception->errorInfo[0] === '23P01') {
                    throw ValidationException::withMessages([
                        'effective_to' => ['The price period overlaps an existing entry for this service and tier.'],
                    ]);
                }

                throw $exception;
            }

            return $locked->refresh()->load('service', 'priceBook.branch');
        });
    }
}
