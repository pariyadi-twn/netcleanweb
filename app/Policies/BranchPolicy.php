<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;

class BranchPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, Branch $branch): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::BranchesView)
            && $this->context->canAccessBranch($branch);
    }

    public function manage(User $user, Branch $branch): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::BranchesManage)
            && $this->context->canAccessBranch($branch);
    }

    public function viewReports(User $user, Branch $branch): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::ReportsView)
            && $this->context->canAccessBranch($branch);
    }
}
