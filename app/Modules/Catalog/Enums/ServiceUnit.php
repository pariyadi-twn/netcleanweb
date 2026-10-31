<?php

namespace App\Modules\Catalog\Enums;

enum ServiceUnit: string
{
    case Kg = 'kg';
    case Pcs = 'pcs';
    case Pair = 'pair';
    case Meter = 'meter';
    case Package = 'package';

    public function requiresWholeQuantity(): bool
    {
        return match ($this) {
            self::Pcs, self::Pair, self::Package => true,
            self::Kg, self::Meter => false,
        };
    }

    public function accepts(string $quantity): bool
    {
        if (! $this->requiresWholeQuantity()) {
            return true;
        }

        $decimal = explode('.', $quantity, 2)[1] ?? '';

        return $decimal === '' || trim($decimal, '0') === '';
    }
}
