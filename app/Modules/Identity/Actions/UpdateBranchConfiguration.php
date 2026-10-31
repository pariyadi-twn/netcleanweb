<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Branch;
use Illuminate\Support\Facades\DB;

final class UpdateBranchConfiguration
{
    private const MUTABLE_ATTRIBUTES = ['name', 'timezone', 'address', 'phone', 'is_active'];

    public function __construct(private readonly AuditRecorder $auditor) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Branch $branch, array $attributes, ?User $actor = null, ?string $reason = null): Branch
    {
        $changes = array_intersect_key($attributes, array_flip(self::MUTABLE_ATTRIBUTES));

        return DB::transaction(function () use ($branch, $changes, $actor, $reason): Branch {
            $before = $branch->only(array_keys($changes));

            $branch->forceFill([
                ...$changes,
                'version' => $branch->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $branch->tenant_id,
                action: AuditAction::BranchConfigurationUpdated,
                subjectType: AuditRecorder::SUBJECT_BRANCH,
                subjectPublicId: $branch->public_id,
                changes: [
                    'before' => $before,
                    'after' => $branch->only(array_keys($changes)),
                ],
                actor: $actor,
                reason: $reason,
            );

            return $branch->refresh();
        });
    }
}
