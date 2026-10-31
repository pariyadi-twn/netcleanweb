<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;

class RefundPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function request(User $user, Payment $payment): bool
    {
        return $user->id === $this->context->userId
            && $payment->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::RefundsRequest)
            && $this->context->canAccessBranch($payment->receivedBranch);
    }

    public function decide(User $user, Refund $refund): bool
    {
        return $user->id === $this->context->userId
            && $refund->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::RefundsApprove)
            && $this->context->canAccessBranch($refund->payment->receivedBranch);
    }

    public function transition(User $user, Refund $refund): bool
    {
        return $this->decide($user, $refund);
    }
}
