<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerConsentRequest;
use App\Http\Resources\Api\V1\CustomerConsentResource;
use App\Modules\Customer\Actions\GetCustomerConsents;
use App\Modules\Customer\Actions\RecordCustomerConsent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerConsentController extends Controller
{
    public function index(string $customer, GetCustomerConsents $get): JsonResponse
    {
        $model = $this->findCustomer($customer);

        Gate::authorize('view', $model);

        return response()->json([
            'data' => CustomerConsentResource::collection($get->handle($model))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(
        StoreCustomerConsentRequest $request,
        string $customer,
        RecordCustomerConsent $record,
    ): JsonResponse {
        $model = $this->findCustomer($customer);

        Gate::authorize('update', $model);

        $consent = $record->handle($model, $request->validated());

        return CustomerConsentResource::make($consent->load('customer'))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    private function findCustomer(string $publicId): Customer
    {
        return Customer::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
