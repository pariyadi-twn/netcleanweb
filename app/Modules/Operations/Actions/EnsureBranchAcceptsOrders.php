<?php

namespace App\Modules\Operations\Actions;

use App\Exceptions\ApiException;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\Branch;

final class EnsureBranchAcceptsOrders
{
    public function handle(Branch $branch): void
    {
        if (! $branch->is_active) {
            throw new ApiException('BRANCH_INACTIVE', 'The branch is inactive and cannot accept orders.', 409);
        }

        if ($branch->tenant()->first()?->status !== TenantStatus::Active) {
            throw new ApiException('TENANT_INACTIVE', 'The tenant is not active and cannot accept orders.', 409);
        }
    }
}
