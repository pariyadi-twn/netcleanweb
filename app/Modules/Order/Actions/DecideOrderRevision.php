<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Order\Enums\OrderRevisionStatus;
use App\Modules\Order\Models\OrderRevision;
use Illuminate\Support\Facades\DB;

final class DecideOrderRevision
{
    public function __construct(
        private readonly ApplyOrderRevision $apply,
        private readonly AuditRecorder $auditor,
    ) {}

    public function handle(
        OrderRevision $revision,
        bool $approve,
        string $reason,
        int $approverMembershipId,
        User $actor,
    ): OrderRevision {
        return DB::transaction(function () use ($revision, $approve, $reason, $approverMembershipId, $actor): OrderRevision {
            $locked = OrderRevision::query()
                ->where('tenant_id', $revision->tenant_id)
                ->whereKey($revision->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== OrderRevisionStatus::Pending) {
                throw new ApiException('ORDER_REVISION_DECIDED', 'The order revision has already been decided.', 409);
            }

            if ($locked->requested_by_membership_id === $approverMembershipId) {
                throw new ApiException('ORDER_REVISION_APPROVAL_CONFLICT', 'The requester cannot approve their own revision.', 403);
            }

            if ($approve) {
                $this->apply->handle($locked, $approverMembershipId, $actor, $reason);
            } else {
                $locked->forceFill([
                    'status' => OrderRevisionStatus::Rejected,
                    'approved_by_membership_id' => $approverMembershipId,
                    'decision_reason' => $reason,
                    'decided_at' => now(),
                    'version' => $locked->version + 1,
                ])->save();
            }

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::OrderRevisionDecided,
                subjectType: AuditRecorder::SUBJECT_ORDER,
                subjectPublicId: $locked->order->public_id,
                changes: [
                    'revision_id' => $locked->public_id,
                    'decision' => $approve ? 'approved' : 'rejected',
                    'difference_rupiah' => $locked->difference_rupiah,
                    'reason' => $reason,
                ],
                actor: $actor,
                reason: $reason,
            );

            return $locked->refresh();
        });
    }
}
