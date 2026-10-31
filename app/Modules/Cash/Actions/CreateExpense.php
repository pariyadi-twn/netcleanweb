<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Enums\ExpenseApprovalStatus;
use App\Modules\Cash\Models\Expense;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class CreateExpense
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    public function handle(Branch $branch, array $attributes, int $membershipId, ?User $actor = null): Expense
    {
        return DB::transaction(function () use ($branch, $attributes, $membershipId, $actor): Expense {
            if (! $this->context->canAccessBranch($branch)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            $expense = new Expense([
                'branch_id' => $branch->id,
                'category' => (string) $attributes['category'],
                'description' => (string) $attributes['description'],
                'amount_rupiah' => (int) $attributes['amount_rupiah'],
                'occurred_at' => $attributes['occurred_at'],
                'approval_status' => ExpenseApprovalStatus::Draft,
                'requested_by_membership_id' => $membershipId,
            ]);
            $expense->tenant_id = $branch->tenant_id;
            $expense->save();

            $this->auditor->record(
                tenantId: $expense->tenant_id,
                action: AuditAction::ExpenseCreated,
                subjectType: AuditRecorder::SUBJECT_EXPENSE,
                subjectPublicId: $expense->public_id,
                changes: ['amount_rupiah' => $expense->amount_rupiah, 'category' => $expense->category],
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $expense->tenant_id,
                eventType: 'expense.created',
                aggregatePublicId: $expense->public_id,
                payload: [
                    'expense_id' => $expense->public_id,
                    'branch_id' => $branch->public_id,
                    'amount_rupiah' => $expense->amount_rupiah,
                    'category' => $expense->category,
                ],
            );

            return $expense->refresh();
        });
    }
}
