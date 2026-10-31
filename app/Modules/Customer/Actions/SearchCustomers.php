<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class SearchCustomers
{
    public function __construct(private readonly NormalizePhoneNumber $phones) {}

    public function handle(int $tenantId, ?string $query, int $perPage, ?string $cursor): CursorPaginator
    {
        $query = trim($query ?? '');
        $digits = $query === '' ? null : $this->phones->searchDigits($query);

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($query !== '', function ($builder) use ($query, $digits): void {
                $builder->where(function ($nested) use ($query, $digits): void {
                    $nested->where('name', 'ilike', '%'.$query.'%');

                    if ($digits !== null) {
                        $nested->orWhere('normalized_phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}
