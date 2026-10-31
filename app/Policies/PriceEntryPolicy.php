<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class PriceEntryPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, PriceEntry $priceEntry): bool
    {
        return $user->id === $this->context->userId
            && $priceEntry->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogView);
    }

    public function update(User $user, PriceEntry $priceEntry): bool
    {
        return $user->id === $this->context->userId
            && $priceEntry->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogManage);
    }
}
