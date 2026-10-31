<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Order\Models\Invoice;

class CreditApprovalPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user, Invoice $invoice): bool
    {
        return $user->id === $this->context->userId
            && $invoice->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CreditManage)
            && $this->context->canAccessBranch($invoice->salesBranch);
    }
}
