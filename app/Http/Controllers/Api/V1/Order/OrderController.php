<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\AcceptOrderRequest;
use App\Http\Requests\Api\V1\Order\SearchOrderRequest;
use App\Http\Resources\Api\V1\BagResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Actions\AcceptOrder;
use App\Modules\Order\Actions\NormalizeOrderInput;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(SearchOrderRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $context = app(TenantContext::class);
        $query = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('sales_branch_id', $context->branchIds)
            ->when(
                $request->filled('order_number'),
                fn ($builder) => $builder->where('order_number', trim((string) $request->input('order_number'))),
            )
            ->with([
                'invoice.adjustments',
                'invoice.allocations',
                'lines',
                'salesBranch',
                'quote',
                'bags.contents.orderLine',
                'bags.order',
                'bags.currentLocation',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->cursorPaginate(
            (int) $request->integer('per_page', 25),
            ['*'],
            'cursor',
            $request->input('cursor'),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Order $order): array => [
                ...(new OrderResource($order))->resolve($request),
                'bags' => BagResource::collection($order->bags)->resolve($request),
            ])->values()->all(),
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    public function store(
        AcceptOrderRequest $request,
        CommandReceiptService $receipts,
        NormalizeOrderInput $normalizer,
        AcceptOrder $accept,
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
        $canUseBranch = $context->canAccessBranch($branch);
        $normalized = $normalizer->handle($request->validated());
        $quotePublicId = Str::lower((string) $request->input('quote_id'));

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'order.accept',
            payload: [
                'client_command_id' => $clientCommandId,
                'quote_id' => $quotePublicId,
                ...$normalized,
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => $canUseBranch && Gate::forUser($request->user())->allows('create', Order::class),
            execute: function () use ($context, $quotePublicId, $normalized, $request, $accept): CommandResult {
                $quote = Quote::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $quotePublicId)
                    ->first();

                if ($quote === null) {
                    throw ValidationException::withMessages([
                        'quote_id' => ['The quote was not found in this tenant.'],
                    ]);
                }

                $order = $accept->handle($quote, $normalized, $request->user(), $context->membershipId);

                return $this->resultFor($order);
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $order = Order::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->with(['invoice.adjustments', 'invoice.allocations', 'lines', 'salesBranch', 'quote'])
                    ->firstOrFail();

                return $this->resultFor($order);
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

    private function resultFor(Order $order): CommandResult
    {
        $order->loadMissing(['invoice.adjustments', 'invoice.allocations', 'lines', 'salesBranch', 'quote']);

        return new CommandResult(
            resourceType: 'order',
            resourcePublicId: $order->public_id,
            data: (new OrderResource($order))->resolve(),
        );
    }
}
