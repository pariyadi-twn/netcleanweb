<?php

namespace App\Modules\Order\Actions;

use App\Support\Idempotency\CanonicalPayload;
use Illuminate\Support\Str;

final class NormalizeOrderInput
{
    public function __construct(private readonly CanonicalPayload $canonical) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $method = (string) $input['fulfillment_method'];
        $branchId = Str::lower((string) $input['branch_id']);
        $pickupBranchId = $method === 'pickup'
            ? Str::lower((string) ($input['pickup_branch_id'] ?? $branchId))
            : null;

        return [
            'branch_id' => $branchId,
            'customer_id' => empty($input['customer_id']) ? null : Str::lower((string) $input['customer_id']),
            'fulfillment_method' => $method,
            'pickup_branch_id' => $pickupBranchId,
            'instructions' => $this->nullableTrimmed($input['instructions'] ?? null),
            'service_discount_percent' => (int) ($input['service_discount_percent'] ?? 0),
            'discount_reason' => $this->nullableTrimmed($input['discount_reason'] ?? null),
            'lines' => array_map(fn (array $line): array => [
                'service_id' => Str::lower((string) $line['service_id']),
                'measured_quantity' => $this->quantity((string) $line['measured_quantity']),
                'tier' => (string) ($line['tier'] ?? 'regular'),
            ], array_values($input['lines'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function requestHash(array $normalized): string
    {
        return $this->canonical->hash('order.input', $normalized);
    }

    private function quantity(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        return $whole.'.'.$fraction;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
