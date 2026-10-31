<?php

namespace App\Modules\Payment\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\RefundEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DecideRefund
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(Refund $refund, bool $approve, int $membershipId, ?string $reason = null, ?User $actor = null): Refund
    {
        return DB::transaction(function () use ($refund, $approve, $membershipId, $reason, $actor): Refund {
            $locked = Refund::query()
                ->where('tenant_id', $refund->tenant_id)
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RefundStatus::Requested) {
                throw new ApiException('REFUND_DECISION_NOT_ALLOWED', 'Only a requested refund can be decided.', 409);
            }
            if (! $this->context->canAccessBranch($locked->payment->receivedBranch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }
            if ($approve && $locked->amount_rupiah > 100000 && $locked->requested_by_membership_id === $membershipId) {
                throw new ApiException('REFUND_APPROVER_CONFLICT', 'A refund above Rp100.000 requires a different approver.', 409);
            }

            $to = $approve ? RefundStatus::Approved : RefundStatus::Rejected;
            $locked->forceFill([
                'status' => $to,
                'approved_by_membership_id' => $approve ? $membershipId : null,
                'version' => $locked->version + 1,
            ])->save();

            $event = new RefundEvent([
                'refund_id' => $locked->id,
                'from_status' => RefundStatus::Requested,
                'to_status' => $to,
                'actor_membership_id' => $membershipId,
                'reason' => $reason,
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::RefundDecided,
                subjectType: AuditRecorder::SUBJECT_REFUND,
                subjectPublicId: $locked->public_id,
                changes: ['status' => $to->value, 'approved_by_membership_id' => $membershipId],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'refund.decided',
                aggregatePublicId: $locked->public_id,
                payload: ['refund_id' => $locked->public_id, 'status' => $to->value],
            );

            return $locked->refresh();
        });
    }
}
