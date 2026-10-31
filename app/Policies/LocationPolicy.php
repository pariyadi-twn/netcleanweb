<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Location;

class LocationPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function view(User $user, Location $location): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::LocationsView)
            && $this->context->canAccessLocation($location);
    }

    public function manage(User $user, Location $location): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::LocationsManage)
            && $this->context->canAccessLocation($location);
    }
}
