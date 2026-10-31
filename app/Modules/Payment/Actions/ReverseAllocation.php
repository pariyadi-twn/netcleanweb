<?php

namespace App\Modules\Payment\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\AllocationReversal;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseAllocation
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(
        PaymentAllocation $allocation,
        int $amountRupiah,
        string $reason,
        int $membershipId,
        ?User $actor = null,
    ): AllocationReversal {
        return DB::transaction(function () use ($allocation, $amountRupiah, $reason, $membershipId, $actor): AllocationReversal {
            $locked = PaymentAllocation::query()
                ->where('tenant_id', $allocation->tenant_id)
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->context->canAccessBranch($locked->payment->receivedBranch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $reversed = (int) $locked->reversals()->sum('amount_rupiah');
            $refunded = (int) $locked->refundAllocations()
                ->whereHas('refund', fn ($query) => $query->where('status', RefundStatus::Succeeded->value))
                ->sum('amount_rupiah');
            $available = (int) $locked->amount_rupiah - $reversed - $refunded;

            if ($amountRupiah <= 0 || $amountRupiah > $available) {
                throw ValidationException::withMessages([
                    'amount_rupiah' => ['The reversal amount exceeds the net allocated amount.'],
                ]);
            }

            $reversal = new AllocationReversal([
                'payment_allocation_id' => $locked->id,
                'amount_rupiah' => $amountRupiah,
                'reason' => $reason,
                'approved_by_membership_id' => $membershipId,
            ]);
            $reversal->tenant_id = $locked->tenant_id;
            $reversal->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::AllocationReversed,
                subjectType: AuditRecorder::SUBJECT_ALLOCATION,
                subjectPublicId: $locked->public_id,
                changes: ['amount_rupiah' => $amountRupiah, 'invoice_id' => $locked->invoice->public_id],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'allocation.reversed',
                aggregatePublicId: $reversal->public_id,
                payload: [
                    'allocation_id' => $locked->public_id,
                    'invoice_id' => $locked->invoice->public_id,
                    'amount_rupiah' => $amountRupiah,
                ],
            );

            return $reversal->refresh();
        });
    }
}
