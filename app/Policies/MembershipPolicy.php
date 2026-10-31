<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\TenantMembership;

class MembershipPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, TenantMembership $membership): bool
    {
        return $user->id === $this->context->userId
            && $membership->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::MembershipsView);
    }

    public function manage(User $user, TenantMembership $membership): bool
    {
        return $user->id === $this->context->userId
            && $membership->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::MembershipsManage);
    }
}
