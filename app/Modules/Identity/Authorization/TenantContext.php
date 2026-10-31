<?php

namespace App\Modules\Identity\Authorization;

use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;

final readonly class TenantContext
{
    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $locationIds
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $userId,
        public int $tenantId,
        public string $tenantPublicId,
        public int $membershipId,
        public string $membershipPublicId,
        public int $permissionVersion,
        public array $branchIds,
        public array $locationIds,
        public array $permissions,
    ) {}

    public function can(PermissionCode $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $branch->tenant_id === $this->tenantId
            && in_array($branch->id, $this->branchIds, true);
    }

    public function canAccessLocation(Location $location): bool
    {
        return $location->tenant_id === $this->tenantId
            && in_array($location->id, $this->locationIds, true);
    }
}
