<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;

class CashRegisterPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId && $this->context->can(PermissionCode::CashView);
    }

    public function view(User $user, CashRegister $register): bool
    {
        return $user->id === $this->context->userId
            && $register->tenant_id === $this->context->tenantId
            && $this->context->canAccessBranch($register->branch)
            && $this->context->can(PermissionCode::CashView);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->id === $this->context->userId
            && $branch->tenant_id === $this->context->tenantId
            && $this->context->canAccessBranch($branch)
            && $this->context->can(PermissionCode::CashManage);
    }
}
