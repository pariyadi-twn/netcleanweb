<?php

namespace App\Modules\Identity\Authorization;

use App\Models\User;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class MembershipAuthorizer
{
    public function contextFor(User $user, Tenant $tenant): ?TenantContext
    {
        if ($tenant->status !== TenantStatus::Active) {
            return null;
        }

        $membership = $user->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if ($membership === null) {
            return null;
        }

        $payload = Cache::remember(
            $this->cacheKey($membership),
            now()->addMinutes(10),
            fn (): array => $this->authorizationPayload($membership),
        );

        return new TenantContext(
            userId: $user->id,
            tenantId: $tenant->id,
            tenantPublicId: $tenant->public_id,
            membershipId: $membership->id,
            membershipPublicId: $membership->public_id,
            permissionVersion: $membership->permission_version,
            branchIds: $payload['branch_ids'],
            locationIds: $payload['location_ids'],
            permissions: $payload['permissions'],
        );
    }

    public function bumpPermissionVersion(TenantMembership $membership): void
    {
        $previousVersion = $membership->permission_version;

        $membership->forceFill(['permission_version' => $previousVersion + 1])->save();

        Cache::forget($this->cacheKey($membership, $previousVersion));
    }

    /**
     * @return array{permissions: list<string>, branch_ids: list<int>, location_ids: list<int>}
     */
    private function authorizationPayload(TenantMembership $membership): array
    {
        $permissions = Permission::query()
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('membership_roles', 'membership_roles.role_id', '=', 'role_permissions.role_id')
            ->where('membership_roles.membership_id', $membership->id)
            ->where('membership_roles.tenant_id', $membership->tenant_id)
            ->where('role_permissions.tenant_id', $membership->tenant_id)
            ->distinct()
            ->orderBy('permissions.code')
            ->pluck('permissions.code')
            ->values()
            ->all();

        $branchIds = DB::table('branch_assignments')
            ->where('membership_id', $membership->id)
            ->where('tenant_id', $membership->tenant_id)
            ->orderBy('branch_id')
            ->pluck('branch_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();

        $locationIds = DB::table('location_assignments')
            ->where('membership_id', $membership->id)
            ->where('tenant_id', $membership->tenant_id)
            ->orderBy('location_id')
            ->pluck('location_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();

        return [
            'permissions' => $permissions,
            'branch_ids' => $branchIds,
            'location_ids' => $locationIds,
        ];
    }

    private function cacheKey(TenantMembership $membership, ?int $version = null): string
    {
        return 'membership-authorization:'.$membership->id.':'.($version ?? $membership->permission_version);
    }
}
