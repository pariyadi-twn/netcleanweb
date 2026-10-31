<?php

namespace App\Support\Idempotency;

use Illuminate\Support\Str;

final class CanonicalPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function hash(string $commandType, array $payload): string
    {
        unset($payload['client_command_id'], $payload['correlation_id']);

        return hash('sha256', $commandType."\0".json_encode(
            $this->normalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
            }

            ksort($value);

            foreach ($value as $key => $item) {
                $value[$key] = $this->normalize($item);
            }

            return $value;
        }

        if (is_string($value) && Str::isUuid($value)) {
            return mb_strtolower($value);
        }

        return $value;
    }
}
