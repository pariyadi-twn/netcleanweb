<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Reporting\Models\ImportBatch;

class ImportBatchPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::ImportsManage);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $user->id === $this->context->userId
            && $batch->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ImportsManage);
    }
}
