<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\SearchCustomerRequest;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\SearchCustomers;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(SearchCustomerRequest $request, SearchCustomers $search): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $context = app(TenantContext::class);
        $paginator = $search->handle(
            $context->tenantId,
            $request->input('q'),
            (int) $request->integer('per_page', 25),
            $request->input('cursor'),
        );

        return CustomerResource::collection($paginator->items())->additional([
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'watermark' => null,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $create): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $context = app(TenantContext::class);
        $customer = $create->handle($context->tenantId, $request->validated(), $request->user());

        return CustomerResource::make($customer)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $customer): CustomerResource
    {
        $model = $this->findCustomer($customer);

        Gate::authorize('view', $model);

        return new CustomerResource($model->load('addresses'));
    }

    private function findCustomer(string $publicId): Customer
    {
        $context = app(TenantContext::class);

        return Customer::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
