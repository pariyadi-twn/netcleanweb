<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\DuplicateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Modules\Customer\Actions\FindCustomerDuplicates;
use App\Modules\Customer\Actions\NormalizePhoneNumber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerDuplicateController extends Controller
{
    public function __invoke(
        DuplicateCustomerRequest $request,
        FindCustomerDuplicates $find,
        NormalizePhoneNumber $phones,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Customer::class);

        $context = app(TenantContext::class);
        $duplicates = $find->handle(
            $context->tenantId,
            $phones->optional($request->input('phone')),
            $request->input('name'),
        );

        return CustomerResource::collection($duplicates)->additional([
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
