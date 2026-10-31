<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Order\Enums\DiscountApprovalStatus;
use App\Modules\Order\Models\DiscountApproval;
use App\Modules\Order\Models\Quote;
use Illuminate\Support\Facades\DB;

final class DecideDiscountApproval
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(
        Quote $quote,
        bool $approve,
        string $reason,
        int $approverMembershipId,
    ): DiscountApproval {
        return DB::transaction(function () use ($quote, $approve, $reason, $approverMembershipId): DiscountApproval {
            $lockedQuote = Quote::query()
                ->where('tenant_id', $quote->tenant_id)
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedQuote->consumed_at !== null) {
                throw new ApiException('QUOTE_CONSUMED', 'The quote has already been consumed.', 409);
            }

            if ($lockedQuote->expires_at->isPast()) {
                throw new ApiException('QUOTE_EXPIRED', 'The quote has expired.', 422);
            }

            $approval = DiscountApproval::query()
                ->where('tenant_id', $lockedQuote->tenant_id)
                ->where('quote_id', $lockedQuote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($approval->status !== DiscountApprovalStatus::Pending) {
                throw new ApiException('DISCOUNT_APPROVAL_DECIDED', 'The discount approval has already been decided.', 409);
            }

            if ($approval->requested_by_membership_id === $approverMembershipId) {
                throw new ApiException('DISCOUNT_APPROVAL_CONFLICT', 'The requester cannot approve their own discount.', 403);
            }

            $status = $approve ? DiscountApprovalStatus::Approved : DiscountApprovalStatus::Rejected;
            $approval->forceFill([
                'status' => $status,
                'approved_by_membership_id' => $approverMembershipId,
                'decision_reason' => $reason,
                'decided_at' => now(),
                'version' => $approval->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $approval->tenant_id,
                action: AuditAction::DiscountApprovalDecided,
                subjectType: AuditRecorder::SUBJECT_QUOTE,
                subjectPublicId: $lockedQuote->public_id,
                changes: [
                    'percent' => $approval->percent,
                    'decision' => $status->value,
                    'reason' => $reason,
                ],
                reason: $reason,
            );

            return $approval->refresh();
        });
    }
}
