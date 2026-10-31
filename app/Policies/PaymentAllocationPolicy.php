<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Payment\Models\PaymentAllocation;

class PaymentAllocationPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function reverse(User $user, PaymentAllocation $allocation): bool
    {
        return $user->id === $this->context->userId
            && $allocation->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::RefundsApprove)
            && $this->context->canAccessBranch($allocation->payment->receivedBranch);
    }
}
