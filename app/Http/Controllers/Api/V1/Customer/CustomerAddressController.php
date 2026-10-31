<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerAddressRequest;
use App\Http\Resources\Api\V1\CustomerAddressResource;
use App\Modules\Customer\Actions\CreateCustomerAddress;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerAddressController extends Controller
{
    public function store(
        StoreCustomerAddressRequest $request,
        string $customer,
        CreateCustomerAddress $create,
    ): JsonResponse {
        $context = app(TenantContext::class);

        $model = Customer::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $customer)
            ->firstOrFail();

        Gate::authorize('update', $model);

        $address = $create->handle($model, $request->validated());

        return CustomerAddressResource::make($address)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }
}
