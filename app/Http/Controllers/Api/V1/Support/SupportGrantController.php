<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\CreateSupportGrantRequest;
use App\Http\Requests\Api\V1\Support\RevokeSupportGrantRequest;
use App\Http\Resources\Api\V1\SupportAccessGrantResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Support\Actions\CreateSupportGrant;
use App\Modules\Support\Actions\RevokeSupportGrant;
use App\Modules\Support\Models\SupportAccessGrant;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupportGrantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SupportAccessGrant::class);
        $context = app(TenantContext::class);

        $grants = SupportAccessGrant::query()
            ->where('tenant_id', $context->tenantId)
            ->with(['platformUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SupportAccessGrantResource::collection($grants);
    }

    public function store(
        CreateSupportGrantRequest $request,
        CreateSupportGrant $create,
    ): JsonResponse {
        Gate::authorize('create', SupportAccessGrant::class);
        $context = app(TenantContext::class);

        $grant = $create->handle(
            $context->tenantId,
            $context->membershipId,
            $request->validated(),
            $request->user(),
        );

        return SupportAccessGrantResource::make($grant->load('platformUser'))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function revoke(
        RevokeSupportGrantRequest $request,
        string $grant,
        RevokeSupportGrant $revoke,
    ): SupportAccessGrantResource {
        $context = app(TenantContext::class);
        $model = SupportAccessGrant::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $grant)
            ->firstOrFail();
        Gate::authorize('revoke', $model);

        $model = $revoke->handle($model, $context->membershipId, $request->string('reason')->toString(), $request->user());

        return new SupportAccessGrantResource($model->load(['platformUser']));
    }

    public function received(Request $request, TenantScope $scope): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user !== null && $user->is_platform_admin, 403);

        $grants = $scope->identity($user, fn () => SupportAccessGrant::query()
            ->where('platform_user_id', $user->id)
            ->with(['tenant', 'platformUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get());

        return SupportAccessGrantResource::collection($grants);
    }
}
