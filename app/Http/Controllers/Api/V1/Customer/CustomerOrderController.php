<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ListCustomerOrdersRequest;
use App\Http\Resources\Api\V1\CustomerOrderResource;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Order;
use App\Support\Http\CorrelationId;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerOrderController extends Controller
{
    public function __invoke(ListCustomerOrdersRequest $request, string $customer): AnonymousResourceCollection
    {
        $context = app(TenantContext::class);
        $model = Customer::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $customer)
            ->firstOrFail();

        Gate::authorize('view', $model);
        Gate::authorize('viewAny', Order::class);

        $paginator = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('customer_id', $model->id)
            ->with(['invoice', 'salesBranch'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                (int) $request->integer('per_page', 25),
                ['*'],
                'cursor',
                $request->input('cursor'),
            );

        return CustomerOrderResource::collection($paginator->items())->additional([
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'watermark' => null,
            ],
        ]);
    }
}
