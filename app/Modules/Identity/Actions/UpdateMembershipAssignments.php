<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateMembershipAssignments
{
    public function __construct(
        private readonly MembershipAuthorizer $authorizer,
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  list<int>  $roleIds
     * @param  list<int>  $branchIds
     * @param  list<int>  $locationIds
     */
    public function handle(
        TenantMembership $membership,
        array $roleIds,
        array $branchIds,
        array $locationIds,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        $roleIds = $this->assertBelongsToTenant(Role::class, $membership->tenant_id, $roleIds, 'roles');
        $branchIds = $this->assertBelongsToTenant(Branch::class, $membership->tenant_id, $branchIds, 'branches');
        $locationIds = $this->assertBelongsToTenant(Location::class, $membership->tenant_id, $locationIds, 'locations');

        DB::transaction(function () use ($membership, $roleIds, $branchIds, $locationIds, $actor, $reason): void {
            $assignments = [
                ['table' => 'membership_roles', 'column' => 'role_id', 'ids' => $roleIds],
                ['table' => 'branch_assignments', 'column' => 'branch_id', 'ids' => $branchIds],
                ['table' => 'location_assignments', 'column' => 'location_id', 'ids' => $locationIds],
            ];

            foreach ($assignments as $assignment) {
                DB::table($assignment['table'])
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('membership_id', $membership->id)
                    ->delete();

                if ($assignment['ids'] !== []) {
                    DB::table($assignment['table'])->insert(array_map(
                        fn (int $id): array => [
                            'tenant_id' => $membership->tenant_id,
                            'membership_id' => $membership->id,
                            $assignment['column'] => $id,
                        ],
                        $assignment['ids'],
                    ));
                }
            }

            $membership->refresh();
            $this->authorizer->bumpPermissionVersion($membership);
            $this->auditor->record(
                tenantId: $membership->tenant_id,
                action: AuditAction::MembershipAssignmentsUpdated,
                subjectType: AuditRecorder::SUBJECT_TENANT_MEMBERSHIP,
                subjectPublicId: $membership->public_id,
                changes: [
                    'role_ids' => $roleIds,
                    'branch_ids' => $branchIds,
                    'location_ids' => $locationIds,
                ],
                actor: $actor,
                reason: $reason,
            );

            $this->outbox->record(
                tenantId: $membership->tenant_id,
                eventType: 'membership.assignments_updated',
                aggregatePublicId: $membership->public_id,
                payload: [
                    'membership_id' => $membership->public_id,
                    'permission_version' => $membership->permission_version,
                    'role_ids' => Role::query()
                        ->where('tenant_id', $membership->tenant_id)
                        ->whereIn('id', $roleIds)
                        ->orderBy('public_id')
                        ->pluck('public_id')
                        ->all(),
                    'branch_ids' => Branch::query()
                        ->where('tenant_id', $membership->tenant_id)
                        ->whereIn('id', $branchIds)
                        ->orderBy('public_id')
                        ->pluck('public_id')
                        ->all(),
                    'location_ids' => Location::query()
                        ->where('tenant_id', $membership->tenant_id)
                        ->whereIn('id', $locationIds)
                        ->orderBy('public_id')
                        ->pluck('public_id')
                        ->all(),
                ],
            );
        });
    }

    /**
     * @param  class-string  $model
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function assertBelongsToTenant(string $model, int $tenantId, array $ids, string $field): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $count = $model::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                $field => ['The selected assignments do not belong to the tenant.'],
            ]);
        }

        return $ids;
    }
}
