<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\DecideRefundRequest;
use App\Http\Requests\Api\V1\Payment\RequestRefundRequest;
use App\Http\Requests\Api\V1\Payment\TransitionRefundRequest;
use App\Http\Resources\Api\V1\RefundResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Payment\Actions\DecideRefund;
use App\Modules\Payment\Actions\RequestRefund;
use App\Modules\Payment\Actions\TransitionRefund;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function store(
        RequestRefundRequest $request,
        string $payment,
        CommandReceiptService $receipts,
        RequestRefund $requestRefund,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $payment = Payment::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($payment))
            ->firstOrFail();

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'refund.request',
            payload: [
                'client_command_id' => $clientCommandId,
                'payment_id' => $payment->public_id,
                'amount_rupiah' => (int) $request->integer('amount_rupiah'),
                'reason' => $request->string('reason')->toString(),
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('requestRefund', $payment),
            execute: function () use ($payment, $request, $requestRefund, $context): CommandResult {
                $refund = $requestRefund->handle(
                    $payment,
                    (int) $request->integer('amount_rupiah'),
                    $request->string('reason')->toString(),
                    $context->membershipId,
                    $request->user(),
                );

                return $this->resultFor($refund);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $refund = Refund::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['payment', 'requestedBy', 'approvedBy', 'cashSession', 'allocations'])
                    ->firstOrFail();

                return $this->resultFor($refund);
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

    public function decide(DecideRefundRequest $request, string $refund, DecideRefund $decideRefund): JsonResponse
    {
        $refund = $this->refund($refund);
        Gate::authorize('decide', $refund);

        $refund = $decideRefund->handle(
            $refund,
            $request->string('decision')->toString() === 'approved',
            app(TenantContext::class)->membershipId,
            $request->input('reason'),
            $request->user(),
        );

        return $this->response($refund);
    }

    public function transition(TransitionRefundRequest $request, string $refund, TransitionRefund $transitionRefund): JsonResponse
    {
        $refund = $this->refund($refund);
        Gate::authorize('transition', $refund);

        $refund = $transitionRefund->handle(
            $refund,
            RefundStatus::from($request->string('status')->toString()),
            app(TenantContext::class)->membershipId,
            $request->input('cash_session_id'),
            $request->input('external_ref'),
            $request->input('reason'),
            $request->user(),
        );

        return $this->response($refund);
    }

    private function refund(string $publicId): Refund
    {
        $context = app(TenantContext::class);

        return Refund::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($publicId))
            ->firstOrFail();
    }

    private function response(Refund $refund): JsonResponse
    {
        $refund->loadMissing(['payment', 'requestedBy', 'approvedBy', 'cashSession', 'allocations']);

        return response()->json([
            'data' => (new RefundResource($refund))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function resultFor(Refund $refund): CommandResult
    {
        $refund->loadMissing(['payment', 'requestedBy', 'approvedBy', 'cashSession', 'allocations']);

        return new CommandResult(
            resourceType: 'refund',
            resourcePublicId: $refund->public_id,
            data: (new RefundResource($refund))->resolve(),
        );
    }
}
