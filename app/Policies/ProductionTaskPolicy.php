<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Location;
use App\Modules\Production\Models\ProductionTask;

class ProductionTaskPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::ProductionView);
    }

    public function plan(User $user, Location $location): bool
    {
        return $user->id === $this->context->userId
            && $location->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ProductionManage)
            && $this->context->canAccessLocation($location);
    }

    public function transition(User $user, ProductionTask $task): bool
    {
        return $user->id === $this->context->userId
            && $task->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::ProductionManage)
            && $this->context->canAccessLocation($task->location);
    }
}
