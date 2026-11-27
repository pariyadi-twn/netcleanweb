<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\ProductionMode;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class UpdateTenantProfile
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    /**
     * @param  array{name: string, timezone: string, production_mode?: string, require_payment_before_handover?: bool}  $attributes
     */
    public function handle(Tenant $tenant, array $attributes, User $actor, ?string $reason = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $attributes, $actor, $reason): Tenant {
            $before = [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'production_mode' => $tenant->production_mode->value,
                'require_payment_before_handover' => (bool) $tenant->require_payment_before_handover,
            ];

            $tenant->forceFill([
                'name' => $attributes['name'],
                'timezone' => $attributes['timezone'],
                'production_mode' => ProductionMode::from($attributes['production_mode'] ?? $tenant->production_mode->value)->value,
                'require_payment_before_handover' => (bool) ($attributes['require_payment_before_handover'] ?? $tenant->require_payment_before_handover),
                'version' => $tenant->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $tenant->id,
                action: AuditAction::TenantProfileUpdated,
                subjectType: AuditRecorder::SUBJECT_TENANT,
                subjectPublicId: $tenant->public_id,
                changes: [
                    'before' => $before,
                    'after' => [
                        'name' => $tenant->name,
                        'timezone' => $tenant->timezone,
                        'production_mode' => $tenant->production_mode->value,
                        'require_payment_before_handover' => (bool) $tenant->require_payment_before_handover,
                    ],
                ],
                actor: $actor,
                reason: $reason,
            );

            return $tenant->refresh();
        });
    }
}
