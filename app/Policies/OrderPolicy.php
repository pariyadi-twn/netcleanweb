<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Models\Order;

class OrderPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::OrdersView);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->id !== $this->context->userId
            || $order->tenant_id !== $this->context->tenantId
            || ! $this->context->can(PermissionCode::OrdersView)) {
            return false;
        }

        $branch = Branch::query()
            ->where('tenant_id', $this->context->tenantId)
            ->whereKey($order->sales_branch_id)
            ->first();

        return $branch !== null && $this->context->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::OrdersManage);
    }

    public function revise(User $user, Order $order): bool
    {
        return $this->view($user, $order)
            && $this->context->can(PermissionCode::OrdersManage);
    }

    public function manageTracking(User $user, Order $order): bool
    {
        return $this->revise($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->revise($user, $order);
    }
}
