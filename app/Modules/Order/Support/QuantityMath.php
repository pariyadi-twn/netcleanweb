<?php

namespace App\Modules\Order\Support;

final class QuantityMath
{
    public function milli(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $fraction;
    }

    public function quantity(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    public function billable(string $measured, string $minimum, string $step): int
    {
        $measuredMilli = $this->milli($measured);
        $minimumMilli = $this->milli($minimum);
        $stepMilli = $this->milli($step);
        $rounded = intdiv($measuredMilli + $stepMilli - 1, $stepMilli) * $stepMilli;

        return max($minimumMilli, $rounded);
    }

    public function money(int $unitPrice, int $quantityMilli): int
    {
        return intdiv(($unitPrice * $quantityMilli) + 500, 1000);
    }

    public function percentage(int $amount, int $percent): int
    {
        return intdiv(($amount * $percent) + 50, 100);
    }
}
