<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Logistics\Models\Handover;
use App\Modules\Order\Models\Order;

class HandoverPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user, Order $order): bool
    {
        return $user->id === $this->context->userId
            && $order->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::HandoversManage)
            && $this->context->canAccessBranch($order->salesBranch);
    }

    public function view(User $user, Handover $handover): bool
    {
        return $user->id === $this->context->userId
            && $handover->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::HandoversManage);
    }
}
