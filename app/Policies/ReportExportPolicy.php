<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
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
            && $this->context->can(PermissionCode::ReportsExport);
    }

    public function download(User $user, ReportExport $export): bool
    {
        return $this->view($user, $export);
    }
}
