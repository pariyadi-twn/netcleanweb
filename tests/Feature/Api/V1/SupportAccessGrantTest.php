<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportAccessGrantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_support_grant_is_tenant_time_and_action_scoped_and_audited(): void
    {
        [$tenant, $ownerToken, $branch, $supportToken, $supportUser, $role, $owner] = $this->scenario();
        $expires = now()->addHour();

        $created = $this->grant($tenant, $ownerToken, $supportUser->email, $expires)
            ->assertCreated()
            ->assertJsonPath('data.allowed_actions', ['branches.view'])
            ->assertJsonPath('data.case_reference', 'CASE-42');

        $grantId = $created->json('data.grant_id');

        $this->app['auth']->forgetGuards();
        $this->withToken($supportToken)
            ->withHeader('X-Support-Grant-ID', $grantId)
            ->getJson('/api/v1/branches/'.$branch->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $branch->public_id);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'support_access.used',
            'actor_user_id' => $supportUser->id,
            'subject_public_id' => $grantId,
        ]);
        $this->assertDatabaseHas('support_access_grants', ['public_id' => $grantId, 'use_count' => 1]);

        $this->app['auth']->forgetGuards();
        $this->withToken($supportToken)
            ->withHeader('X-Support-Grant-ID', $grantId)
            ->getJson('/api/v1/customers')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($supportToken)
            ->withHeader('X-Support-Grant-ID', $grantId)
            ->postJson('/api/v1/orders/'.Str::uuid().'/cancel', [])
            ->assertForbidden();

        $received = $this->withToken($supportToken)
            ->getJson('/api/v1/support/received-grants')
            ->assertOk()
            ->assertJsonPath('data.0.grant_id', $grantId)
            ->assertJsonPath('data.0.tenant_id', $tenant->public_id);

        $this->assertNotNull($received->json('data.0.tenant_id'));
    }

    public function test_revoke_and_expiry_are_immediate_and_non_read_actions_are_rejected(): void
    {
        [$tenant, $ownerToken, $branch, $supportToken, $supportUser, $role, $owner] = $this->scenario();

        $this->grant($tenant, $ownerToken, $supportUser->email, now()->addHours(25))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['expires_at']]]);

        $this->withToken($ownerToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/support/grants', [
                'platform_email' => $supportUser->email,
                'case_reference' => 'CASE-BAD',
                'reason' => 'Mencoba akses tulis',
                'allowed_actions' => ['payments.manage'],
                'expires_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['allowed_actions']]]);

        $grantId = $this->grant($tenant, $ownerToken, $supportUser->email, now()->addMinute())
            ->assertCreated()
            ->json('data.grant_id');

        $this->withToken($ownerToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/support/grants/'.$grantId.'/revoke', ['reason' => 'Kasus selesai'])
            ->assertOk()
            ->assertJsonPath('data.revoked_at', fn ($value): bool => $value !== null);

        $this->app['auth']->forgetGuards();
        $this->withToken($supportToken)
            ->withHeader('X-Support-Grant-ID', $grantId)
            ->getJson('/api/v1/branches/'.$branch->public_id)
            ->assertForbidden();

        $expiring = $this->grant($tenant, $ownerToken, $supportUser->email, now()->addMinute())
            ->assertCreated()
            ->json('data.grant_id');

        Carbon::setTestNow(now()->addMinutes(2));
        $this->app['auth']->forgetGuards();
        $this->withToken($supportToken)
            ->withHeader('X-Support-Grant-ID', $expiring)
            ->getJson('/api/v1/branches/'.$branch->public_id)
            ->assertForbidden();
    }

    public function test_support_grant_requires_platform_user_and_tenant_permission(): void
    {
        [$tenant, $ownerToken, $branch, $supportToken, $supportUser, $role, $owner] = $this->scenario();
        $normalUser = User::factory()->create();

        $this->grant($tenant, $ownerToken, $normalUser->email, now()->addHour())
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['platform_email']]]);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::BranchesView->value)->sole()->id],
            $owner,
            'Remove support grant permission',
        );

        $this->grant($tenant, $ownerToken, $supportUser->email, now()->addHour())
            ->assertForbidden();
    }

    private function grant(Tenant $tenant, string $token, string $email, \DateTimeInterface $expires)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['X-Support-Grant-ID' => ''])
            ->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/support/grants', [
                'platform_email' => $email,
                'case_reference' => 'CASE-42',
                'reason' => 'Investigasi tiket pelanggan',
                'allowed_actions' => ['branches.view'],
                'expires_at' => $expires->format(\DateTimeInterface::ATOM),
            ]);
    }

    /** @return array{Tenant, string, Branch, string, User, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $ownerMembership = TenantMembership::factory()->for($tenant)->for($owner)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::SupportGrant, PermissionCode::BranchesView])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($ownerMembership, [$role->id], [$branch->id], [], $owner, 'Bootstrap support owner');

        $supportUser = User::factory()->platformAdmin()->create();
        $ownerToken = app(IssueDeviceToken::class)->handle($owner, (string) Str::uuid(), 'Owner device')['token']->plainTextToken;
        $supportToken = app(IssueDeviceToken::class)->handle($supportUser, (string) Str::uuid(), 'Support device')['token']->plainTextToken;

        return [$tenant, $ownerToken, $branch, $supportToken, $supportUser, $role, $owner];
    }
}
