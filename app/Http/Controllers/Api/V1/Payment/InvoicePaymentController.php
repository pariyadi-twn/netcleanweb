<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\RecordPaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Actions\RecordPayment;
use App\Modules\Payment\Models\Payment;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoicePaymentController extends Controller
{
    public function store(
        RecordPaymentRequest $request,
        string $invoice,
        CommandReceiptService $receipts,
        RecordPayment $recordPayment,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $invoice = Invoice::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($invoice))
            ->firstOrFail();

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'payment.record',
            payload: [
                'client_command_id' => $clientCommandId,
                'invoice_id' => $invoice->public_id,
                ...$request->validated(),
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('create', Payment::class),
            execute: function () use ($invoice, $request, $recordPayment, $context): CommandResult {
                $payment = $recordPayment->handle(
                    $invoice,
                    $request->validated(),
                    $context->membershipId,
                    $request->user(),
                );

                return $this->resultFor($payment);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $payment = Payment::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['allocations.invoice.adjustments', 'allocations.invoice.allocations'])
                    ->firstOrFail();

                return $this->resultFor($payment);
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

    private function resultFor(Payment $payment): CommandResult
    {
        $payment->loadMissing(['allocations.invoice.adjustments', 'allocations.invoice.allocations']);

        return new CommandResult(
            resourceType: 'payment',
            resourcePublicId: $payment->public_id,
            data: (new PaymentResource($payment))->resolve(),
        );
    }
}
