<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\DecideDataRequestRequest;
use App\Http\Requests\Api\V1\Platform\RejectDataRequestRequest;
use App\Http\Resources\Api\V1\DataRequestResource;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Lifecycle\Actions\DecideDataRequest;
use App\Modules\Lifecycle\Actions\ProcessDataDeletion;
use App\Modules\Lifecycle\Models\DataRequest;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformDataRequestController extends Controller
{
    public function index(Request $request, TenantScope $scope): AnonymousResourceCollection
    {
        $this->authorizePlatform($request);

        $requests = $scope->identity($request->user(), fn () => DataRequest::query()
            ->with(['tenant'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get());

        return DataRequestResource::collection($requests);
    }

    public function verify(
        DecideDataRequestRequest $request,
        string $dataRequest,
        TenantScope $scope,
        DecideDataRequest $decide,
    ): JsonResponse {
        return $this->decide($request, $dataRequest, $scope, $decide, true);
    }

    public function reject(
        RejectDataRequestRequest $request,
        string $dataRequest,
        TenantScope $scope,
        DecideDataRequest $decide,
    ): JsonResponse {
        return $this->decide($request, $dataRequest, $scope, $decide, false);
    }

    public function process(
        Request $request,
        string $dataRequest,
        TenantScope $scope,
        ProcessDataDeletion $process,
    ): JsonResponse {
        $this->authorizePlatform($request);
        $result = $scope->identity($request->user(), function () use ($request, $dataRequest, $scope, $process): DataRequest {
            $model = DataRequest::query()->where('public_id', $dataRequest)->firstOrFail();

            return $scope->forTenantId($model->tenant_id, $request->user()->id, fn (): DataRequest => $process->handle(
                $model,
                $request->user()->id,
                $request->user(),
            ));
        });

        return response()->json([
            'data' => (new DataRequestResource($result->load('customer')))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function decide(
        Request $request,
        string $dataRequest,
        TenantScope $scope,
        DecideDataRequest $decide,
        bool $approve,
    ): JsonResponse {
        $this->authorizePlatform($request);
        $result = $scope->identity($request->user(), function () use ($request, $dataRequest, $scope, $decide, $approve): DataRequest {
            $model = DataRequest::query()->where('public_id', $dataRequest)->firstOrFail();

            return $scope->forTenantId($model->tenant_id, $request->user()->id, fn (): DataRequest => $decide->handle(
                $model,
                $approve,
                (string) $request->input('verification_method', 'platform_review'),
                $request->user()->id,
                $request->input('reason'),
                $request->user(),
            ));
        });

        return response()->json([
            'data' => (new DataRequestResource($result))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
    }
}
