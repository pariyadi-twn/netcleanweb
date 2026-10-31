<?php

namespace App\Modules\Customer\Actions;

use Illuminate\Validation\ValidationException;

final class NormalizePhoneNumber
{
    public function handle(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/\A0[0-9]{7,14}\z/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => ['The phone number must be a valid Indonesian phone number.'],
            ]);
        }

        return $digits;
    }

    public function optional(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->handle($value);
    }

    public function searchDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value ?? '') ?? '';

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return preg_match('/\A0[0-9]{2,14}\z/', $digits) === 1 ? $digits : null;
    }
}
