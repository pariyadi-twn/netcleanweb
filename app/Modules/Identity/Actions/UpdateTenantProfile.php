<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class UpdateTenantProfile
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    /**
     * @param  array{name: string, timezone: string}  $attributes
     */
    public function handle(Tenant $tenant, array $attributes, User $actor, ?string $reason = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $attributes, $actor, $reason): Tenant {
            $before = $tenant->only(['name', 'timezone']);

            $tenant->forceFill([
                'name' => $attributes['name'],
                'timezone' => $attributes['timezone'],
                'version' => $tenant->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $tenant->id,
                action: AuditAction::TenantProfileUpdated,
                subjectType: AuditRecorder::SUBJECT_TENANT,
                subjectPublicId: $tenant->public_id,
                changes: [
                    'before' => $before,
                    'after' => $tenant->only(['name', 'timezone']),
                ],
                actor: $actor,
                reason: $reason,
            );

            return $tenant->refresh();
        });
    }
}
