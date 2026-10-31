<?php

namespace App\Modules\Identity\Support;

final class RecoveryCodes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return list<string> */
    public function generate(int $count): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $code = '';

            for ($index = 0; $index < 12; $index++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $codes[] = implode('-', str_split($code, 4));
        }

        return $codes;
    }

    public function hash(string $code): string
    {
        return hash('sha256', $this->normalize($code));
    }

    public function normalize(string $code): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }
}
