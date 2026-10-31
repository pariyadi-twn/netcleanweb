<?php

namespace App\Modules\Cash\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Cash\Enums\ExpenseApprovalStatus;
use App\Modules\Cash\Models\CashEntry;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\Expense;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideExpense
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(
        Expense $expense,
        bool $approve,
        ?string $cashSessionPublicId,
        int $membershipId,
        ?string $reason = null,
        ?User $actor = null,
    ): Expense {
        return DB::transaction(function () use ($expense, $approve, $cashSessionPublicId, $membershipId, $reason, $actor): Expense {
            $locked = Expense::query()
                ->where('tenant_id', $expense->tenant_id)
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->approval_status !== ExpenseApprovalStatus::Draft) {
                throw new ApiException('EXPENSE_DECISION_NOT_ALLOWED', 'Only a draft expense can be decided.', 409);
            }
            if (! $this->context->canAccessBranch($locked->branch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $session = null;
            if ($approve) {
                $session = CashSession::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('public_id', $cashSessionPublicId)
                    ->where('status', 'open')
                    ->with('register.branch')
                    ->lockForUpdate()
                    ->first();
                if ($session === null || $session->register->branch_id !== $locked->branch_id) {
                    throw ValidationException::withMessages([
                        'cash_session_id' => ['An open cash session for the expense branch is required.'],
                    ]);
                }
                if (! $this->context->canAccessBranch($session->register->branch)) {
                    throw new AuthorizationException('This action is unauthorized.');
                }
            }

            $status = $approve ? ExpenseApprovalStatus::Approved : ExpenseApprovalStatus::Rejected;
            $locked->forceFill([
                'approval_status' => $status,
                'cash_session_id' => $session?->id,
                'approved_by_membership_id' => $membershipId,
                'approved_at' => $approve ? now() : null,
                'version' => $locked->version + 1,
            ])->save();

            if ($approve) {
                $entry = new CashEntry([
                    'cash_session_id' => $session->id,
                    'kind' => 'expense_out',
                    'amount_rupiah' => -$locked->amount_rupiah,
                    'reference_type' => 'expense',
                    'reference_id' => $locked->id,
                ]);
                $entry->tenant_id = $locked->tenant_id;
                $entry->save();
            }

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ExpenseDecided,
                subjectType: AuditRecorder::SUBJECT_EXPENSE,
                subjectPublicId: $locked->public_id,
                changes: ['approval_status' => $status->value, 'cash_session_id' => $session?->public_id],
                reason: $reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: $approve ? 'expense.approved' : 'expense.rejected',
                aggregatePublicId: $locked->public_id,
                payload: [
                    'expense_id' => $locked->public_id,
                    'amount_rupiah' => $locked->amount_rupiah,
                    'approval_status' => $status->value,
                ],
            );

            return $locked->refresh()->load(['branch', 'cashSession', 'approvedBy']);
        });
    }
}
