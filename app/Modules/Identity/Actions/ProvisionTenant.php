<?php

namespace App\Modules\Identity\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Authorization\ProvisioningScope;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\ProvisioningStatus;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\ProvisioningRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Idempotency\CanonicalPayload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProvisionTenant
{
    public function __construct(
        private readonly CanonicalPayload $canonical,
        private readonly ProvisioningScope $scope,
        private readonly SyncRolePermissions $syncPermissions,
        private readonly UpdateMembershipAssignments $assignments,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{tenant: Tenant, branch: Branch, membership: TenantMembership, replay: bool}
     */
    public function handle(User $user, array $payload, string $clientCommandId): array
    {
        $requestHash = $this->canonical->hash('tenant.provisioning', $payload);
        $request = $this->reserve($user, $clientCommandId, $requestHash);

        if ($request->request_hash !== $requestHash) {
            throw $this->payloadMismatch();
        }

        if ($request->status === ProvisioningStatus::Completed) {
            return $this->replay($request);
        }

        if ($request->status === ProvisioningStatus::Rejected) {
            throw new ApiException($request->error_code ?? 'PROVISIONING_REJECTED', 'Tenant provisioning was rejected.', 422);
        }

        return DB::transaction(function () use ($request, $requestHash, $user, $payload): array {
            $locked = ProvisioningRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->request_hash !== $requestHash) {
                throw $this->payloadMismatch();
            }

            if ($locked->status === ProvisioningStatus::Completed) {
                return $this->replay($locked);
            }

            if ($locked->status === ProvisioningStatus::Rejected) {
                throw new ApiException($locked->error_code ?? 'PROVISIONING_REJECTED', 'Tenant provisioning was rejected.', 422);
            }

            $tenantId = (int) DB::selectOne("SELECT nextval(pg_get_serial_sequence('tenants', 'id')) AS id")->id;
            $this->scope->activateTenant($tenantId);

            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => $payload['organization_name'],
                'slug' => $this->slug($payload),
                'timezone' => $payload['timezone'],
                'status' => TenantStatus::Active->value,
            ]);

            $tenant = Tenant::query()->findOrFail($tenantId);

            $branch = new Branch([
                'code' => $payload['branch_code'] ?? 'MAIN',
                'name' => $payload['branch_name'],
                'timezone' => $payload['branch_timezone'] ?? $payload['timezone'],
                'address' => $payload['branch_address'],
                'phone' => $payload['branch_phone'] ?? null,
                'is_active' => true,
            ]);
            $branch->tenant_id = $tenant->id;
            $branch->save();

            $ownerRole = new Role([
                'code' => RoleCode::Owner->value,
                'name' => 'Pemilik',
            ]);
            $ownerRole->tenant_id = $tenant->id;
            $ownerRole->is_system = true;
            $ownerRole->save();

            $this->syncPermissions->handle(
                $ownerRole,
                Permission::query()->pluck('id')->all(),
                $user,
                'Owner role provisioning',
            );

            $membership = new TenantMembership;
            $membership->forceFill([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'status' => MembershipStatus::Active,
                'permission_version' => 1,
                'invited_by_user_id' => null,
            ])->save();

            $this->assignments->handle(
                $membership,
                [$ownerRole->id],
                [$branch->id],
                [],
                $user,
                'Initial owner assignment',
            );

            $locked->forceFill([
                'status' => ProvisioningStatus::Completed,
                'tenant_id' => $tenant->id,
                'tenant_public_id' => $tenant->public_id,
                'branch_id' => $branch->id,
                'branch_public_id' => $branch->public_id,
                'membership_id' => $membership->id,
                'membership_public_id' => $membership->public_id,
                'completed_at' => now(),
                'error_code' => null,
            ])->save();

            return [
                'tenant' => $tenant->refresh(),
                'branch' => $branch->refresh(),
                'membership' => $membership->refresh(),
                'replay' => false,
            ];
        });
    }

    private function reserve(User $user, string $clientCommandId, string $requestHash): ProvisioningRequest
    {
        try {
            return DB::transaction(fn (): ProvisioningRequest => ProvisioningRequest::query()->create([
                'user_id' => $user->id,
                'client_command_id' => $clientCommandId,
                'request_hash' => $requestHash,
                'status' => ProvisioningStatus::Processing,
            ]));
        } catch (UniqueConstraintViolationException) {
            return ProvisioningRequest::query()
                ->where('user_id', $user->id)
                ->where('client_command_id', $clientCommandId)
                ->firstOrFail();
        }
    }

    /**
     * @return array{tenant: Tenant, branch: Branch, membership: TenantMembership, replay: bool}
     */
    private function replay(ProvisioningRequest $request): array
    {
        if ($request->tenant_id === null || $request->branch_id === null || $request->membership_id === null) {
            throw new ApiException('PROVISIONING_INCOMPLETE', 'Tenant provisioning is incomplete.', 409);
        }

        $this->scope->activateTenant($request->tenant_id);

        return [
            'tenant' => Tenant::query()->findOrFail($request->tenant_id),
            'branch' => Branch::query()->findOrFail($request->branch_id),
            'membership' => TenantMembership::query()->findOrFail($request->membership_id),
            'replay' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function slug(array $payload): string
    {
        $slug = Str::slug((string) ($payload['slug'] ?? $payload['organization_name']));

        if ($slug === '') {
            $slug = 'tenant';
        }

        return $slug.'-'.mb_strtolower(Str::random(6));
    }

    private function payloadMismatch(): ApiException
    {
        return new ApiException(
            'IDEMPOTENCY_PAYLOAD_MISMATCH',
            'The idempotency key was already used with a different payload.',
            409,
        );
    }
}
