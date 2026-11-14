<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRole
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly SyncRolePermissions $syncPermissions,
    ) {}

    /**
     * @param  list<int>  $permissionIds
     */
    public function handle(User $actor, int $tenantId, string $code, string $name, array $permissionIds = [], ?string $reason = null): Role
    {
        $code = mb_strtolower(trim($code));

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)) {
            throw ValidationException::withMessages([
                'code' => ['The code must use lowercase letters, numbers, dashes, or underscores.'],
            ]);
        }

        if (Role::query()->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ['The code has already been taken for this tenant.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $tenantId, $code, $name, $permissionIds, $reason): Role {
            $role = new Role(['code' => $code, 'name' => $name]);
            $role->tenant_id = $tenantId;
            $role->is_system = false;
            $role->save();

            $this->auditor->record(
                tenantId: $tenantId,
                action: AuditAction::RoleCreated,
                subjectType: AuditRecorder::SUBJECT_ROLE,
                subjectPublicId: $role->public_id,
                changes: ['code' => $code, 'name' => $name],
                actor: $actor,
                reason: $reason,
            );

            if ($permissionIds !== []) {
                $this->syncPermissions->handle($role, $permissionIds, $actor, $reason);
            }

            return $role->refresh();
        });
    }
}
