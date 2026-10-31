<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ProvisionTenantRequest;
use App\Modules\Identity\Actions\ProvisionTenant;
use App\Modules\Identity\Authorization\ProvisioningScope;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProvisionTenantController extends Controller
{
    public function __invoke(
        ProvisionTenantRequest $request,
        ProvisionTenant $provision,
        ProvisioningScope $scope,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $result = $scope->run(
            $request->user(),
            fn (): array => $provision->handle($request->user(), $request->validated(), $clientCommandId),
        );

        return response()->json([
            'data' => [
                'tenant_id' => $result['tenant']->public_id,
                'tenant_name' => $result['tenant']->name,
                'branch_id' => $result['branch']->public_id,
                'branch_name' => $result['branch']->name,
                'membership_id' => $result['membership']->public_id,
            ],
            'meta' => [
                'idempotent_replay' => $result['replay'],
                'correlation_id' => CorrelationId::current(),
            ],
        ], $result['replay'] ? 200 : 201);
    }
}
