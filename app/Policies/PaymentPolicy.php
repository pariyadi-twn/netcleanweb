<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Payment\Models\Payment;

class PaymentPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $this->context->userId
            && $payment->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::PaymentsView);
    }

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::PaymentsManage);
    }

    public function createForBranch(User $user, Branch $branch): bool
    {
        return $this->create($user)
            && $branch->tenant_id === $this->context->tenantId
            && $this->context->canAccessBranch($branch);
    }

    public function requestRefund(User $user, Payment $payment): bool
    {
        return $user->id === $this->context->userId
            && $payment->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::RefundsRequest)
            && $this->context->canAccessBranch($payment->receivedBranch);
    }
}
