<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\Complaint;

class ComplaintPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user, Order $order): bool
    {
        return $user->id === $this->context->userId
            && $order->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ComplaintsManage)
            && $this->context->canAccessBranch($order->salesBranch);
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $user->id === $this->context->userId
            && $complaint->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ComplaintsManage)
            && $this->context->canAccessBranch($complaint->order->salesBranch);
    }

    public function manage(User $user, Complaint $complaint): bool
    {
        return $this->view($user, $complaint);
    }
}
