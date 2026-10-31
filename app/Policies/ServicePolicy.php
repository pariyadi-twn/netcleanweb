<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class ServicePolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CatalogView);
    }

    public function view(User $user, Service $service): bool
    {
        return $user->id === $this->context->userId
            && $service->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogView);
    }

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CatalogManage);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->id === $this->context->userId
            && $service->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CatalogManage);
    }
}
