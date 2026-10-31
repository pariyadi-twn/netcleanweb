<?php

namespace App\Modules\Support\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Support\Models\SupportAccessGrant;
use Illuminate\Support\Facades\DB;

final class RevokeSupportGrant
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(SupportAccessGrant $grant, int $membershipId, string $reason, ?User $actor = null): SupportAccessGrant
    {
        return DB::transaction(function () use ($grant, $membershipId, $reason, $actor): SupportAccessGrant {
            $locked = SupportAccessGrant::query()
                ->where('tenant_id', $grant->tenant_id)
                ->whereKey($grant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_membership_id' => $membershipId,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::SupportGrantRevoked,
                subjectType: AuditRecorder::SUBJECT_SUPPORT_GRANT,
                subjectPublicId: $locked->public_id,
                changes: ['case_reference' => $locked->case_reference],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'support_grant.revoked',
                aggregatePublicId: $locked->public_id,
                payload: ['support_grant_id' => $locked->public_id],
            );

            return $locked->refresh();
        });
    }
}
