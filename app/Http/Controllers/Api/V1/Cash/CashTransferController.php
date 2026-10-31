<?php

namespace App\Http\Controllers\Api\V1\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cash\ConfirmCashTransferRequest;
use App\Http\Resources\Api\V1\CashTransferResource;
use App\Modules\Cash\Actions\ConfirmCashTransfer;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashTransferController extends Controller
{
    public function store(
        ConfirmCashTransferRequest $request,
        CommandReceiptService $receipts,
        ConfirmCashTransfer $transfer,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $payload = $request->validated();

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'cash_transfer.confirm',
            payload: $payload,
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('create', CashTransfer::class),
            execute: function () use ($payload, $transfer, $context): CommandResult {
                $cashTransfer = $transfer->handle($payload, $context->membershipId, request()->user());

                return $this->resultFor($cashTransfer);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $cashTransfer = CashTransfer::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['fromRegister', 'toRegister', 'fromSession', 'toSession', 'confirmedBy'])
                    ->firstOrFail();

                return $this->resultFor($cashTransfer);
            },
        );

        return response()->json([
            'data' => $result->data,
            'meta' => ['idempotent_replay' => $result->replay, 'correlation_id' => CorrelationId::current()],
        ], $result->replay ? 200 : 201);
    }

    private function resultFor(CashTransfer $transfer): CommandResult
    {
        $transfer->loadMissing(['fromRegister', 'toRegister', 'fromSession', 'toSession', 'confirmedBy']);

        return new CommandResult(
            resourceType: 'cash_transfer',
            resourcePublicId: $transfer->public_id,
            data: (new CashTransferResource($transfer))->resolve(),
        );
    }
}
