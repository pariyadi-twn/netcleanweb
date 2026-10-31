<?php

namespace App\Modules\Catalog\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

final class EffectivePeriod
{
    /**
     * @return array{CarbonImmutable, CarbonImmutable|null}
     */
    public function fromDates(string $timezone, string $from, ?string $to): array
    {
        $zone = new DateTimeZone($timezone);
        $start = CarbonImmutable::createFromFormat('Y-m-d', $from, $zone)->startOfDay();
        $end = $to === null
            ? null
            : CarbonImmutable::createFromFormat('Y-m-d', $to, $zone)->addDay()->startOfDay();

        return [$start->utc(), $end?->utc()];
    }

    public function localDate(DateTimeInterface $utc, string $timezone): string
    {
        return CarbonImmutable::instance($utc)->setTimezone(new DateTimeZone($timezone))->toDateString();
    }

    public function localEndDate(DateTimeInterface $exclusiveUntil, string $timezone): string
    {
        return CarbonImmutable::instance($exclusiveUntil)
            ->setTimezone(new DateTimeZone($timezone))
            ->subDay()
            ->toDateString();
    }
}
