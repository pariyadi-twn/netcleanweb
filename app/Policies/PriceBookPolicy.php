<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class PriceBookPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CatalogView);
    }

    public function view(User $user, PriceBook $priceBook): bool
    {
        return $user->id === $this->context->userId
            && $priceBook->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogView);
    }

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CatalogManage);
    }

    public function update(User $user, PriceBook $priceBook): bool
    {
        return $user->id === $this->context->userId
            && $priceBook->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogManage);
    }
}
