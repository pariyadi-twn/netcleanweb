<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingCalendarFixtureTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = json_decode(
            (string) file_get_contents(base_path('docs/fixtures/pricing-calendar.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function test_br05_fixture_produces_the_approved_total_and_balance(): void
    {
        $example = $this->fixture['examples']['br05'];
        $service = $this->fixture['services'];

        $billableKg = $this->billable($example['measured_kg'], $service['kiloan']);
        $kiloanLine = (int) $billableKg * $service['kiloan']['tenant_price_rupiah'];
        $bedCoverLine = $example['bed_cover_quantity'] * $service['bed_cover']['tenant_price_rupiah'];
        $subtotal = $kiloanLine + $bedCoverLine;
        $discount = intdiv($subtotal * $this->fixture['discount_example']['service_percent'], 100);
        $delivery = $this->fixture['delivery']['fee_rupiah'];
        $total = $subtotal - $discount + $delivery;

        $this->assertSame('3.0', $billableKg);
        $this->assertSame($example['kiloan_line_rupiah'], $kiloanLine);
        $this->assertSame($example['bed_cover_line_rupiah'], $bedCoverLine);
        $this->assertSame($example['service_subtotal_rupiah'], $subtotal);
        $this->assertSame($example['discount_rupiah'], $discount);
        $this->assertSame($example['delivery_rupiah'], $delivery);
        $this->assertSame($example['total_rupiah'], $total);
        $this->assertSame($example['balance_rupiah'], $total - $example['dp_rupiah']);
    }

    public function test_express_fixture_applies_surcharge_after_service_discount_and_excludes_delivery(): void
    {
        $example = $this->fixture['examples']['express'];
        $discountedService = $example['service_subtotal_rupiah'] - $example['discount_rupiah'];
        $surcharge = intdiv($discountedService * $this->fixture['express']['surcharge_percent'], 100);
        $total = $discountedService + $surcharge + $example['delivery_rupiah'];

        $this->assertSame($example['discounted_service_rupiah'], $discountedService);
        $this->assertSame($example['express_surcharge_rupiah'], $surcharge);
        $this->assertSame($example['total_rupiah'], $total);
        $this->assertFalse($this->fixture['delivery']['discountable']);
        $this->assertFalse($this->fixture['delivery']['express_surcharge']);
    }

    public function test_quantity_is_rounded_up_before_minimum_is_applied(): void
    {
        $service = $this->fixture['services']['kiloan'];
        $rounding = $this->fixture['examples']['quantity_rounding'];

        $this->assertSame('3.0', $this->billable('2.3', $service));
        $this->assertSame($rounding['billable_kg'], $this->billable($rounding['measured_kg'], $service));
        $this->assertSame(15, $this->fixture['quote']['ttl_minutes']);
    }

    /** @return array<string, array{string, int, string}> */
    public static function deadlines(): array
    {
        return [
            'regular before cutoff' => ['accepted_before_cutoff', 2, 'regular_due_before_cutoff'],
            'express before cutoff' => ['accepted_before_cutoff', 1, 'express_due_before_cutoff'],
            'regular after cutoff skips holiday' => ['accepted_after_cutoff', 2, 'regular_due_after_cutoff_skips_holiday'],
            'express after cutoff' => ['accepted_after_cutoff', 1, 'express_due_after_cutoff'],
            'regular before holiday' => ['accepted_before_holiday', 2, 'regular_due_skips_holiday'],
            'express before holiday' => ['accepted_before_holiday', 1, 'express_due_skips_holiday'],
        ];
    }

    #[DataProvider('deadlines')]
    public function test_deadline_fixture_skips_closed_days_and_holidays(string $acceptedKey, int $workingDays, string $expectedKey): void
    {
        $calendar = $this->fixture['calendar'];
        $deadline = $this->fixture['examples']['deadline'];
        $acceptedAt = new DateTimeImmutable($deadline[$acceptedKey]);

        $actual = $this->addWorkingDays($acceptedAt, $workingDays, $calendar);

        $this->assertSame($deadline[$expectedKey], $actual->format('c'));
    }

    /**
     * @param  array<string, mixed>  $service
     */
    private function billable(string $measured, array $service): string
    {
        $step = (float) $service['step'];
        $minimum = (float) $service['minimum_quantity'];
        $quantity = max($minimum, ceil((float) $measured / $step) * $step);

        return number_format($quantity, 1, '.', '');
    }

    /**
     * @param  array<string, mixed>  $calendar
     */
    private function addWorkingDays(DateTimeImmutable $acceptedAt, int $workingDays, array $calendar): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) $calendar['timezone_example']);
        $local = $acceptedAt->setTimezone($timezone);
        $cutoff = $this->timeOn($local, (string) $calendar['cutoff_time']);

        $date = $this->isWorkingDay($local, $calendar) && $local < $cutoff
            ? $local
            : $this->nextWorkingDay($local->modify('+1 day'), $calendar);

        for ($day = 0; $day < $workingDays; $day++) {
            $date = $this->nextWorkingDay($date->modify('+1 day'), $calendar);
        }

        return $date->setTime(
            (int) substr((string) $calendar['deadline_local_time'], 0, 2),
            (int) substr((string) $calendar['deadline_local_time'], 3, 2),
        );
    }

    /**
     * @param  array<string, mixed>  $calendar
     */
    private function nextWorkingDay(DateTimeImmutable $date, array $calendar): DateTimeImmutable
    {
        while (! $this->isWorkingDay($date, $calendar)) {
            $date = $date->modify('+1 day');
        }

        return $date;
    }

    /**
     * @param  array<string, mixed>  $calendar
     */
    private function isWorkingDay(DateTimeImmutable $date, array $calendar): bool
    {
        $weekday = strtolower($date->format('l'));
        $day = $date->format('Y-m-d');

        return in_array($weekday, $calendar['open_days'], true)
            && ! in_array($weekday, $calendar['closed_days'], true)
            && ! in_array($day, $calendar['holidays'], true);
    }

    private function timeOn(DateTimeImmutable $date, string $time): DateTimeImmutable
    {
        return $date->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
    }
}
