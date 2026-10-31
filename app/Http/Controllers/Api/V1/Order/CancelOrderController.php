<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\CancelOrderRequest;
use App\Http\Resources\Api\V1\OrderCancellationResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Order\Actions\CancelOrder;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CancelOrderController extends Controller
{
    public function store(
        CancelOrderRequest $request,
        string $order,
        CommandReceiptService $receipts,
        CancelOrder $cancelOrder,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $order = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($order))
            ->firstOrFail();

        $requiresApproval = in_array($order->operational_status, [OrderStatus::Ready, OrderStatus::PartiallyReady], true);

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'order.cancel',
            payload: [
                'client_command_id' => $clientCommandId,
                'order_id' => $order->public_id,
                'expected_version' => (int) $request->integer('expected_version'),
                'reason' => $request->string('reason')->toString(),
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('cancel', $order)
                && (! $requiresApproval || $context->can(PermissionCode::CancellationsApprove)),
            execute: function () use ($order, $request, $cancelOrder, $context): CommandResult {
                $outcome = $cancelOrder->handle(
                    $order,
                    (int) $request->integer('expected_version'),
                    $request->string('reason')->toString(),
                    $context->membershipId,
                    $request->user(),
                );

                return $this->resultFor($outcome['order'], $outcome['event']);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $order = Order::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['invoice.adjustments', 'invoice.allocations', 'bags'])
                    ->firstOrFail();
                $event = OrderEvent::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('order_id', $order->id)
                    ->where('event_type', 'cancelled')
                    ->latest('id')
                    ->firstOrFail();

                return $this->resultFor($order, $event);
            },
        );

        return response()->json([
            'data' => $result->data,
            'meta' => ['idempotent_replay' => $result->replay, 'correlation_id' => CorrelationId::current()],
        ], $result->replay ? 200 : 201);
    }

    private function resultFor(Order $order, OrderEvent $event): CommandResult
    {
        $order->loadMissing(['invoice.adjustments', 'invoice.allocations', 'bags']);

        return new CommandResult(
            resourceType: 'order',
            resourcePublicId: $order->public_id,
            data: (new OrderCancellationResource($order, $event))->resolve(),
        );
    }
}
