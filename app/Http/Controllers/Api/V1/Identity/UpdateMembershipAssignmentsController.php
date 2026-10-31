<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\UpdateMembershipAssignmentsRequest;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateMembershipAssignmentsController extends Controller
{
    public function __invoke(
        UpdateMembershipAssignmentsRequest $request,
        string $membership,
        CommandReceiptService $receipts,
        UpdateMembershipAssignments $action,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();

        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $target = TenantMembership::query()->where('public_id', $membership)->firstOrFail();

        Gate::authorize('manage', $target);

        $roleIds = $this->resolveIds(Role::class, $request->array('role_ids'), 'role_ids', $context->tenantId);
        $branchIds = $this->resolveIds(Branch::class, $request->array('branch_ids'), 'branch_ids', $context->tenantId);
        $locationIds = $this->resolveIds(Location::class, $request->array('location_ids'), 'location_ids', $context->tenantId);
        $reason = $request->input('reason');

        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'membership.assignments.update',
            payload: [
                'client_command_id' => $clientCommandId,
                'role_ids' => $roleIds,
                'branch_ids' => $branchIds,
                'location_ids' => $locationIds,
                'reason' => $reason,
            ],
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('manage', $target),
            execute: function () use ($action, $target, $roleIds, $branchIds, $locationIds, $request, $reason): CommandResult {
                $action->handle($target, $roleIds, $branchIds, $locationIds, $request->user(), $reason);

                return $this->resultFor($target->refresh());
            },
            resolve: fn ($receipt): CommandResult => $this->resultFor(
                TenantMembership::query()->where('public_id', $receipt->result_public_id)->firstOrFail(),
            ),
        );

        return response()->json([
            'data' => $result->data,
            'meta' => [
                'idempotent_replay' => $result->replay,
                'correlation_id' => CorrelationId::current(),
            ],
        ]);
    }

    /**
     * @param  class-string  $model
     * @param  list<string>  $publicIds
     * @return list<int>
     */
    private function resolveIds(string $model, array $publicIds, string $field, int $tenantId): array
    {
        $publicIds = array_values(array_unique(array_map(
            fn (string $id): string => mb_strtolower($id),
            $publicIds,
        )));

        if ($publicIds === []) {
            return [];
        }

        $map = $model::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('public_id', $publicIds)
            ->pluck('id', 'public_id');

        if ($map->count() !== count($publicIds)) {
            throw ValidationException::withMessages([
                $field => ['One or more records do not belong to the tenant.'],
            ]);
        }

        $ids = array_map(fn (string $publicId): int => (int) $map[$publicId], $publicIds);
        sort($ids);

        return $ids;
    }

    private function resultFor(TenantMembership $membership): CommandResult
    {
        $membership->load(['roles', 'branches', 'locations']);

        return new CommandResult(
            resourceType: 'tenant_membership',
            resourcePublicId: $membership->public_id,
            data: [
                'membership_id' => $membership->public_id,
                'permission_version' => $membership->permission_version,
                'role_ids' => $membership->roles->pluck('public_id')->sort()->values()->all(),
                'branch_ids' => $membership->branches->pluck('public_id')->sort()->values()->all(),
                'location_ids' => $membership->locations->pluck('public_id')->sort()->values()->all(),
            ],
        );
    }
}
