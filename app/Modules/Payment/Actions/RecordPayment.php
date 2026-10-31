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
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPayment
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array{method: string, amount_rupiah: int, cash_received_rupiah?: int|null, cash_session_id?: string|null}  $attributes
     */
    public function handle(Invoice $invoice, array $attributes, int $membershipId, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($invoice, $attributes, $membershipId, $actor): Payment {
            $locked = Invoice::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->order()->where('operational_status', 'cancelled')->exists()) {
                throw new ApiException('ORDER_CANCELLED', 'Payments cannot be recorded for a cancelled order.', 409);
            }

            $amount = (int) $attributes['amount_rupiah'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount_rupiah' => ['The payment amount must be positive.']]);
            }

            $method = (string) $attributes['method'];
            $session = null;
            $receivedBranchId = $locked->sales_branch_id;
            if ($method === 'cash') {
                $session = CashSession::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('public_id', $attributes['cash_session_id'] ?? null)
                    ->where('status', 'open')
                    ->with('register.branch')
                    ->lockForUpdate()
                    ->first();

                if ($session === null) {
                    throw ValidationException::withMessages([
                        'cash_session_id' => ['An open cash session is required for cash payments.'],
                    ]);
                }
                $receivedBranchId = $session->register->branch_id;
                if (! $this->context->canAccessBranch($session->register->branch)) {
                    throw new AuthorizationException('This action is unauthorized.');
                }
            } elseif ($method !== 'bank_manual') {
                throw ValidationException::withMessages(['method' => ['The payment method is not enabled in P0.']]);
            }

            if ($method === 'bank_manual' && ! $this->context->canAccessBranch($locked->salesBranch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $cashReceived = $method === 'cash'
                ? (int) ($attributes['cash_received_rupiah'] ?? $amount)
                : $amount;
            if ($cashReceived < $amount) {
                throw ValidationException::withMessages([
                    'cash_received_rupiah' => ['Cash received cannot be less than the accepted payment.'],
                ]);
            }

            $original = $locked->original_total_rupiah + (int) $locked->adjustments()->sum('amount_rupiah');
            $paid = $locked->netAllocatedRupiah();
            $balance = $original - $paid;
            $allocated = min($amount, max(0, $balance));
            $unallocated = $amount - $allocated;

            $payment = new Payment([
                'customer_id' => $locked->customer_id,
                'received_branch_id' => $receivedBranchId,
                'cash_session_id' => $session?->id,
                'method' => $method,
                'amount_rupiah' => $amount,
                'currency' => 'IDR',
                'cash_received_rupiah' => $cashReceived,
                'change_rupiah' => $cashReceived - $amount,
                'unallocated_rupiah' => $unallocated,
                'received_at' => now(),
                'received_by_membership_id' => $membershipId,
            ]);
            $payment->tenant_id = $locked->tenant_id;
            $payment->save();

            if ($allocated > 0) {
                $allocation = new PaymentAllocation([
                    'payment_id' => $payment->id,
                    'invoice_id' => $locked->id,
                    'amount_rupiah' => $allocated,
                ]);
                $allocation->tenant_id = $locked->tenant_id;
                $allocation->save();
            }

            if ($method === 'cash') {
                $entry = new CashEntry([
                    'cash_session_id' => $session->id,
                    'kind' => 'payment_in',
                    'amount_rupiah' => $amount,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                ]);
                $entry->tenant_id = $locked->tenant_id;
                $entry->save();
            }

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::PaymentRecorded,
                subjectType: AuditRecorder::SUBJECT_PAYMENT,
                subjectPublicId: $payment->public_id,
                changes: [
                    'invoice_id' => $locked->public_id,
                    'amount_rupiah' => $amount,
                    'allocated_rupiah' => $allocated,
                    'unallocated_rupiah' => $unallocated,
                    'method' => $method,
                ],
                actor: $actor,
            );

            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'payment.recorded',
                aggregatePublicId: $payment->public_id,
                payload: [
                    'payment_id' => $payment->public_id,
                    'invoice_id' => $locked->public_id,
                    'amount_rupiah' => $amount,
                    'allocated_rupiah' => $allocated,
                    'unallocated_rupiah' => $unallocated,
                    'method' => $method,
                ],
            );

            return $payment->refresh()->load(['allocations.invoice', 'receivedBranch']);
        });
    }
}
