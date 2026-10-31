<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncRolePermissions
{
    public function __construct(
        private readonly MembershipAuthorizer $authorizer,
        private readonly AuditRecorder $auditor,
    ) {}

    /**
     * @param  list<int>  $permissionIds
     */
    public function handle(Role $role, array $permissionIds, ?User $actor = null, ?string $reason = null): void
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        if ($permissionIds !== []) {
            $count = Permission::query()->whereIn('id', $permissionIds)->count();

            if ($count !== count($permissionIds)) {
                throw ValidationException::withMessages([
                    'permissions' => ['One or more permissions do not exist.'],
                ]);
            }
        }

        DB::transaction(function () use ($role, $permissionIds, $actor, $reason): void {
            DB::table('role_permissions')
                ->where('tenant_id', $role->tenant_id)
                ->where('role_id', $role->id)
                ->delete();

            if ($permissionIds !== []) {
                DB::table('role_permissions')->insert(array_map(
                    fn (int $permissionId): array => [
                        'tenant_id' => $role->tenant_id,
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ],
                    $permissionIds,
                ));
            }

            $memberships = TenantMembership::query()
                ->where('tenant_id', $role->tenant_id)
                ->whereExists(function ($query) use ($role): void {
                    $query->selectRaw('1')
                        ->from('membership_roles')
                        ->whereColumn('membership_roles.membership_id', 'tenant_memberships.id')
                        ->where('membership_roles.tenant_id', $role->tenant_id)
                        ->where('membership_roles.role_id', $role->id);
                })
                ->get();

            foreach ($memberships as $membership) {
                $this->authorizer->bumpPermissionVersion($membership);
            }

            $this->auditor->record(
                tenantId: $role->tenant_id,
                action: AuditAction::RolePermissionsUpdated,
                subjectType: AuditRecorder::SUBJECT_ROLE,
                subjectPublicId: $role->public_id,
                changes: ['permission_ids' => $permissionIds],
                actor: $actor,
                reason: $reason,
            );
        });
    }
}
