<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class CashTransferPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CashManage);
    }
}
