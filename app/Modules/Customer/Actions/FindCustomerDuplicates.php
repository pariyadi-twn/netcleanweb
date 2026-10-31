<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Collection;

final class FindCustomerDuplicates
{
    /**
     * @return Collection<int, Customer>
     */
    public function handle(int $tenantId, ?string $normalizedPhone, ?string $name, ?int $excludeCustomerId = null): Collection
    {
        $name = $name === null ? null : mb_strtolower(trim($name));

        if ($normalizedPhone === null && ($name === null || $name === '')) {
            return collect();
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($excludeCustomerId !== null, fn ($query) => $query->where('id', '!=', $excludeCustomerId))
            ->where(function ($query) use ($normalizedPhone, $name): void {
                if ($normalizedPhone !== null) {
                    $query->where('normalized_phone', $normalizedPhone);
                } else {
                    $query->whereRaw('LOWER(name) = ?', [$name]);
                }

                if ($normalizedPhone !== null && $name !== null && $name !== '') {
                    $query->orWhereRaw('LOWER(name) = ?', [$name]);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();
    }
}
