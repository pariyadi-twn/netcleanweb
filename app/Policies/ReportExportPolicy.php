<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Models\ReportExport;

class ReportExportPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::ReportsExport);
    }

    public function view(User $user, ReportExport $export): bool
    {
        return $user->id === $this->context->userId
            && $export->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ReportsExport)
            && $this->exportBranchIsAccessible($export);
    }

    public function download(User $user, ReportExport $export): bool
    {
        return $this->view($user, $export);
    }

    private function exportBranchIsAccessible(ReportExport $export): bool
    {
        $branchPublicId = $export->filters['branch_id'] ?? null;
        if (! is_string($branchPublicId) || $branchPublicId === '') {
            return false;
        }

        $branch = Branch::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('public_id', $branchPublicId)
            ->first();

        return $branch !== null && $this->context->canAccessBranch($branch);
    }
}
