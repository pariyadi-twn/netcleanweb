<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\UpdateServicePublicationRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Modules\Catalog\Actions\SetServicePublication;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Support\Facades\Gate;

class ServicePublicationController extends Controller
{
    public function __invoke(
        UpdateServicePublicationRequest $request,
        string $service,
        SetServicePublication $publication,
    ): ServiceResource {
        $model = Service::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $service)
            ->firstOrFail();

        Gate::authorize('update', $model);

        return new ServiceResource($publication->handle(
            $model,
            $request->boolean('is_active'),
            $request->user(),
            $request->input('reason'),
        ));
    }
}
