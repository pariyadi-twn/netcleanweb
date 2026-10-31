<?php

namespace App\Modules\Payment\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Cash\Models\CashEntry;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\RefundAllocation;
use App\Modules\Payment\Models\RefundEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionRefund
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(
        Refund $refund,
        RefundStatus $to,
        int $membershipId,
        ?string $cashSessionPublicId = null,
        ?string $externalRef = null,
        ?string $reason = null,
        ?User $actor = null,
    ): Refund {
        return DB::transaction(function () use ($refund, $to, $membershipId, $cashSessionPublicId, $externalRef, $reason, $actor): Refund {
            $locked = Refund::query()
                ->where('tenant_id', $refund->tenant_id)
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();
            $from = $locked->status;

            $allowed = match ($from) {
                RefundStatus::Requested => [RefundStatus::Cancelled],
                RefundStatus::Approved => [RefundStatus::Processing, RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Cancelled],
                RefundStatus::Processing => [RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Cancelled],
                default => [],
            };
            if (! in_array($to, $allowed, true)) {
                throw new ApiException('REFUND_TRANSITION_NOT_ALLOWED', 'The refund status transition is not allowed.', 409);
            }

            $payment = Payment::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->context->canAccessBranch($payment->receivedBranch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $cashSession = null;
            if ($to === RefundStatus::Succeeded && $payment->method === 'cash') {
                $cashSession = CashSession::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('public_id', $cashSessionPublicId)
                    ->where('status', 'open')
                    ->with('register.branch')
                    ->lockForUpdate()
                    ->first();
                if ($cashSession === null) {
                    throw ValidationException::withMessages([
                        'cash_session_id' => ['An open cash session is required for a cash refund.'],
                    ]);
                }
                if (! $this->context->canAccessBranch($cashSession->register->branch)) {
                    throw new AuthorizationException('This action is unauthorized.');
                }
            }

            if ($to === RefundStatus::Succeeded) {
                $this->allocate($locked, $payment);

                if ($cashSession !== null) {
                    $entry = new CashEntry([
                        'cash_session_id' => $cashSession->id,
                        'kind' => 'refund_out',
                        'amount_rupiah' => -$locked->amount_rupiah,
                        'reference_type' => 'refund',
                        'reference_id' => $locked->id,
                    ]);
                    $entry->tenant_id = $locked->tenant_id;
                    $entry->save();
                }
            }

            $locked->forceFill([
                'status' => $to,
                'cash_session_id' => $cashSession?->id,
                'external_ref' => $externalRef,
                'succeeded_at' => $to === RefundStatus::Succeeded ? now() : null,
                'version' => $locked->version + 1,
            ])->save();

            $event = new RefundEvent([
                'refund_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_membership_id' => $membershipId,
                'provider_ref' => $externalRef,
                'reason' => $reason,
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::RefundStatusChanged,
                subjectType: AuditRecorder::SUBJECT_REFUND,
                subjectPublicId: $locked->public_id,
                changes: ['from_status' => $from->value, 'to_status' => $to->value],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'refund.'.$to->value,
                aggregatePublicId: $locked->public_id,
                payload: [
                    'refund_id' => $locked->public_id,
                    'payment_id' => $payment->public_id,
                    'amount_rupiah' => $locked->amount_rupiah,
                    'status' => $to->value,
                ],
            );

            return $locked->refresh()->load('allocations');
        });
    }

    private function allocate(Refund $refund, Payment $payment): void
    {
        $allocations = PaymentAllocation::query()
            ->where('tenant_id', $payment->tenant_id)
            ->where('payment_id', $payment->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        Invoice::query()
            ->where('tenant_id', $payment->tenant_id)
            ->whereIn('id', $allocations->pluck('invoice_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $refund->amount_rupiah;
        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $reversed = (int) $allocation->reversals()->sum('amount_rupiah');
            $refunded = (int) $allocation->refundAllocations()
                ->whereHas('refund', fn ($query) => $query->where('status', RefundStatus::Succeeded->value))
                ->sum('amount_rupiah');
            $available = (int) $allocation->amount_rupiah - $reversed - $refunded;

            if ($available <= 0) {
                continue;
            }

            $amount = min($remaining, $available);
            $refundAllocation = new RefundAllocation([
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'payment_allocation_id' => $allocation->id,
                'amount_rupiah' => $amount,
            ]);
            $refundAllocation->tenant_id = $refund->tenant_id;
            $refundAllocation->save();
            $remaining -= $amount;
        }
    }
}
