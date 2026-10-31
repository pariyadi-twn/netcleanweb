<?php

namespace App\Http\Controllers\Api\V1\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cash\CreateExpenseRequest;
use App\Http\Requests\Api\V1\Cash\DecideExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Modules\Cash\Actions\CreateExpense;
use App\Modules\Cash\Actions\DecideExpense;
use App\Modules\Cash\Models\Expense;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function store(
        CreateExpenseRequest $request,
        CommandReceiptService $receipts,
        CreateExpense $createExpense,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id'))
            ->firstOrFail();

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'expense.create',
            payload: [
                'client_command_id' => $clientCommandId,
                'branch_id' => $branch->public_id,
                ...$request->safe()->except('client_command_id', 'branch_id'),
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('create', [Expense::class, $branch]),
            execute: function () use ($branch, $request, $createExpense, $context): CommandResult {
                $expense = $createExpense->handle($branch, $request->validated(), $context->membershipId, $request->user());

                return $this->resultFor($expense);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $expense = Expense::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['branch', 'cashSession', 'requestedBy', 'approvedBy'])
                    ->firstOrFail();

                return $this->resultFor($expense);
            },
        );

        return response()->json([
            'data' => $result->data,
            'meta' => ['idempotent_replay' => $result->replay, 'correlation_id' => CorrelationId::current()],
        ], $result->replay ? 200 : 201);
    }

    public function decide(DecideExpenseRequest $request, string $expense, DecideExpense $decideExpense): JsonResponse
    {
        $context = app(TenantContext::class);
        $expense = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($expense))
            ->firstOrFail();
        Gate::authorize('decide', $expense);

        $expense = $decideExpense->handle(
            $expense,
            $request->string('decision')->toString() === 'approved',
            $request->input('cash_session_id'),
            $context->membershipId,
            $request->input('reason'),
            $request->user(),
        );
        $expense->loadMissing(['branch', 'cashSession', 'requestedBy', 'approvedBy']);

        return response()->json([
            'data' => (new ExpenseResource($expense))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function resultFor(Expense $expense): CommandResult
    {
        $expense->loadMissing(['branch', 'cashSession', 'requestedBy', 'approvedBy']);

        return new CommandResult(
            resourceType: 'expense',
            resourcePublicId: $expense->public_id,
            data: (new ExpenseResource($expense))->resolve(),
        );
    }
}
