<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateDataRequestRequest;
use App\Http\Resources\Api\V1\DataRequestResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Lifecycle\Actions\CreateDataRequest;
use App\Modules\Lifecycle\Models\DataRequest;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DataRequestController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', DataRequest::class);
        $context = app(TenantContext::class);

        return DataRequestResource::collection(
            DataRequest::query()
                ->where('tenant_id', $context->tenantId)
                ->with(['customer'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        );
    }

    public function store(CreateDataRequestRequest $request, CreateDataRequest $create): JsonResponse
    {
        Gate::authorize('create', DataRequest::class);
        $context = app(TenantContext::class);

        $model = $create->handle(
            $context->tenantId,
            $context->membershipId,
            $request->string('customer_id')->toString(),
            $request->string('reason')->toString(),
            $request->user(),
        );

        return DataRequestResource::make($model->load('customer'))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }
}
