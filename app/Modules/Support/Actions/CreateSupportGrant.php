<?php

namespace App\Modules\Support\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Support\Models\SupportAccessGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSupportGrant
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  array{platform_email: string, case_reference: string, reason: string, allowed_actions: array<int, string>, expires_at: string}  $attributes
     */
    public function handle(int $tenantId, int $approvedByMembershipId, array $attributes, ?User $actor = null): SupportAccessGrant
    {
        return DB::transaction(function () use ($tenantId, $approvedByMembershipId, $attributes, $actor): SupportAccessGrant {
            $platformUser = User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower(trim($attributes['platform_email']))])
                ->where('is_platform_admin', true)
                ->where('status', UserStatus::Active->value)
                ->first();

            if ($platformUser === null) {
                throw ValidationException::withMessages([
                    'platform_email' => ['The selected user must be an active platform support user.'],
                ]);
            }

            $actions = array_values(array_unique($attributes['allowed_actions']));
            if (array_diff($actions, SupportAccessGrant::READ_ACTIONS) !== []) {
                throw ValidationException::withMessages([
                    'allowed_actions' => ['Only read actions can be granted to support.'],
                ]);
            }

            $grant = new SupportAccessGrant([
                'platform_user_id' => $platformUser->id,
                'approved_by_membership_id' => $approvedByMembershipId,
                'case_reference' => $attributes['case_reference'],
                'reason' => $attributes['reason'],
                'allowed_actions' => $actions,
                'expires_at' => $attributes['expires_at'],
            ]);
            $grant->tenant_id = $tenantId;
            $grant->save();

            $this->auditor->record(
                tenantId: $tenantId,
                action: AuditAction::SupportGrantCreated,
                subjectType: AuditRecorder::SUBJECT_SUPPORT_GRANT,
                subjectPublicId: $grant->public_id,
                changes: [
                    'platform_user_id' => $platformUser->public_id,
                    'case_reference' => $grant->case_reference,
                    'allowed_actions' => $actions,
                    'expires_at' => $grant->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
                reason: $grant->reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $tenantId,
                eventType: 'support_grant.created',
                aggregatePublicId: $grant->public_id,
                payload: [
                    'support_grant_id' => $grant->public_id,
                    'case_reference' => $grant->case_reference,
                    'expires_at' => $grant->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                ],
            );

            return $grant->refresh();
        });
    }
}
