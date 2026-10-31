<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\ReverseAllocationRequest;
use App\Http\Resources\Api\V1\AllocationReversalResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Payment\Actions\ReverseAllocation;
use App\Modules\Payment\Models\AllocationReversal;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AllocationReversalController extends Controller
{
    public function store(
        ReverseAllocationRequest $request,
        string $allocation,
        CommandReceiptService $receipts,
        ReverseAllocation $reverseAllocation,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $allocation = PaymentAllocation::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($allocation))
            ->firstOrFail();

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'allocation.reverse',
            payload: [
                'client_command_id' => $clientCommandId,
                'allocation_id' => $allocation->public_id,
                'amount_rupiah' => (int) $request->integer('amount_rupiah'),
                'reason' => $request->string('reason')->toString(),
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('reverse', $allocation),
            execute: function () use ($allocation, $request, $reverseAllocation, $context): CommandResult {
                $reversal = $reverseAllocation->handle(
                    $allocation,
                    (int) $request->integer('amount_rupiah'),
                    $request->string('reason')->toString(),
                    $context->membershipId,
                    $request->user(),
                );

                return $this->resultFor($reversal);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $reversal = AllocationReversal::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['paymentAllocation.invoice', 'approvedBy'])
                    ->firstOrFail();

                return $this->resultFor($reversal);
            },
        );

        return response()->json([
            'data' => $result->data,
            'meta' => [
                'idempotent_replay' => $result->replay,
                'correlation_id' => CorrelationId::current(),
            ],
        ], $result->replay ? 200 : 201);
    }

    private function resultFor(AllocationReversal $reversal): CommandResult
    {
        $reversal->loadMissing(['paymentAllocation.invoice', 'approvedBy']);

        return new CommandResult(
            resourceType: 'allocation_reversal',
            resourcePublicId: $reversal->public_id,
            data: (new AllocationReversalResource($reversal))->resolve(),
        );
    }
}
