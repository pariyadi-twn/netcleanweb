<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Cash\Models\Expense;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;

class ExpensePolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user, Branch $branch): bool
    {
        return $user->id === $this->context->userId
            && $branch->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ExpensesManage)
            && $this->context->canAccessBranch($branch);
    }

    public function decide(User $user, Expense $expense): bool
    {
        return $user->id === $this->context->userId
            && $expense->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ExpensesApprove)
            && $this->context->canAccessBranch($expense->branch);
    }
}
