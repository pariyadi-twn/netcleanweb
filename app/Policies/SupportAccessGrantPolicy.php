<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Support\Models\SupportAccessGrant;

class SupportAccessGrantPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::SupportGrant);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function revoke(User $user, SupportAccessGrant $grant): bool
    {
        return $this->viewAny($user)
            && $grant->tenant_id === $this->context->tenantId;
    }
}
