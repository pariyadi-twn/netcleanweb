<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Identity\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreatePriceBook
{
    /**
     * @param  array{name: string, branch_id?: string|null}  $attributes
     */
    public function handle(int $tenantId, array $attributes): PriceBook
    {
        return DB::transaction(function () use ($tenantId, $attributes): PriceBook {
            $branchId = $this->branchId($tenantId, $attributes['branch_id'] ?? null);
            $scope = $branchId ?? 0;

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'catalog-price-book:'.$tenantId.':'.$scope,
            ]);

            PriceBook::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('customer_id')
                ->when(
                    $branchId === null,
                    fn ($query) => $query->whereNull('branch_id'),
                    fn ($query) => $query->where('branch_id', $branchId),
                )
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (PriceBook $book): void {
                    $book->forceFill([
                        'is_active' => false,
                        'version' => $book->version + 1,
                    ])->save();
                });

            $book = new PriceBook([
                'name' => $attributes['name'],
                'branch_id' => $branchId,
                'is_active' => true,
            ]);
            $book->tenant_id = $tenantId;
            $book->save();

            return $book->load('entries.service', 'branch');
        });
    }

    private function branchId(int $tenantId, ?string $branchPublicId): ?int
    {
        if ($branchPublicId === null) {
            return null;
        }

        $branch = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $branchPublicId)
            ->first();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch must belong to this tenant.'],
            ]);
        }

        return $branch->id;
    }
}
