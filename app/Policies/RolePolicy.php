<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Role;

class RolePolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function manage(User $user, Role $role): bool
    {
        return $user->id === $this->context->userId
            && $role->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::RolesManage);
    }
}
