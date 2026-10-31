<?php

namespace App\Modules\Payment\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\RefundEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestRefund
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(Payment $payment, int $amountRupiah, string $reason, int $membershipId, ?User $actor = null): Refund
    {
        return DB::transaction(function () use ($payment, $amountRupiah, $reason, $membershipId, $actor): Refund {
            $locked = Payment::query()
                ->where('tenant_id', $payment->tenant_id)
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->received_branch_id !== null && ! $this->context->canAccessBranch($locked->receivedBranch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $refunded = (int) Refund::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('payment_id', $locked->id)
                ->where('status', RefundStatus::Succeeded->value)
                ->sum('amount_rupiah');
            $reserved = (int) Refund::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('payment_id', $locked->id)
                ->whereIn('status', [
                    RefundStatus::Requested->value,
                    RefundStatus::Approved->value,
                    RefundStatus::Processing->value,
                ])
                ->sum('amount_rupiah');
            $available = $locked->amount_rupiah - $refunded - $reserved;

            if ($amountRupiah <= 0 || $amountRupiah > $available) {
                throw ValidationException::withMessages([
                    'amount_rupiah' => ['The refund amount exceeds the refundable balance.'],
                ]);
            }

            $refund = new Refund([
                'payment_id' => $locked->id,
                'amount_rupiah' => $amountRupiah,
                'status' => RefundStatus::Requested,
                'requested_by_membership_id' => $membershipId,
                'reason' => $reason,
            ]);
            $refund->tenant_id = $locked->tenant_id;
            $refund->save();

            $this->event($refund, null, RefundStatus::Requested, $membershipId, $reason);
            $this->auditor->record(
                tenantId: $refund->tenant_id,
                action: AuditAction::RefundRequested,
                subjectType: AuditRecorder::SUBJECT_REFUND,
                subjectPublicId: $refund->public_id,
                changes: ['payment_id' => $locked->public_id, 'amount_rupiah' => $amountRupiah],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $refund->tenant_id,
                eventType: 'refund.requested',
                aggregatePublicId: $refund->public_id,
                payload: [
                    'refund_id' => $refund->public_id,
                    'payment_id' => $locked->public_id,
                    'amount_rupiah' => $amountRupiah,
                    'status' => RefundStatus::Requested->value,
                ],
            );

            return $refund->refresh();
        });
    }

    private function event(Refund $refund, ?RefundStatus $from, RefundStatus $to, int $actorId, string $reason): void
    {
        $event = new RefundEvent([
            'refund_id' => $refund->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_membership_id' => $actorId,
            'reason' => $reason,
        ]);
        $event->tenant_id = $refund->tenant_id;
        $event->save();
    }
}
