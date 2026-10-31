<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class CashSessionPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, CashSession $session): bool
    {
        return $user->id === $this->context->userId
            && $session->tenant_id === $this->context->tenantId
            && $this->context->canAccessBranch($session->register->branch)
            && $this->context->can(PermissionCode::CashView);
    }

    public function open(User $user, CashRegister $register): bool
    {
        return $user->id === $this->context->userId
            && $register->tenant_id === $this->context->tenantId
            && $this->context->canAccessBranch($register->branch)
            && $this->context->can(PermissionCode::CashManage);
    }

    public function close(User $user, CashSession $session): bool
    {
        return $this->view($user, $session)
            && $this->context->can(PermissionCode::CashManage);
    }

    public function reopen(User $user, CashSession $session): bool
    {
        return $this->view($user, $session)
            && $this->context->can(PermissionCode::CashReopen);
    }
}
