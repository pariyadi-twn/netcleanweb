<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Actions\EnsureBranchAcceptsOrders;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_branch_configuration_is_returned_updated_and_audited(): void
    {
        [$tenant, $user, , $branch, $token] = $this->scenario();
        $createdAt = $branch->created_at;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/branches/'.$branch->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $branch->public_id)
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.is_active', true);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/branches/'.$branch->public_id, [
                'name' => 'Cabang Baru',
                'timezone' => 'Asia/Makassar',
                'is_active' => false,
                'reason' => 'Temporary closure',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Cabang Baru')
            ->assertJsonPath('data.timezone', 'Asia/Makassar')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.version', 2);

        $branch->refresh();

        $this->assertSame('BR-TEST', $branch->code);
        $this->assertSame($tenant->id, $branch->tenant_id);
        $this->assertTrue($branch->created_at->equalTo($createdAt));

        $log = AuditLog::query()->where('action', AuditAction::BranchConfigurationUpdated->value)->sole();
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame('branch', $log->subject_type);
        $this->assertSame($branch->public_id, $log->subject_public_id);
        $this->assertSame('Temporary closure', $log->reason);
        $this->assertFalse($log->redacted_changes['after']['is_active']);
        $this->assertSame('Asia/Makassar', $log->redacted_changes['after']['timezone']);

        try {
            app(EnsureBranchAcceptsOrders::class)->handle($branch);
            $this->fail('An inactive branch must reject order acceptance.');
        } catch (ApiException $exception) {
            $this->assertSame('BRANCH_INACTIVE', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }

        $activeBranch = $this->activeBranch($tenant);
        app(EnsureBranchAcceptsOrders::class)->handle($activeBranch);
        $this->assertTrue($activeBranch->is_active);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        [$tenant, , , $branch, $token] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/branches/'.$branch->public_id, ['timezone' => 'Mars/Olympus'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['field_errors' => ['timezone']]]);
    }

    public function test_branch_viewer_cannot_update_configuration(): void
    {
        [$tenant, $user, $role, $branch, $token] = $this->scenario();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::BranchesView->value)->sole()->id],
            $user,
            'Downgrade to viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/branches/'.$branch->public_id, ['name' => 'Denied'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_branch_from_another_tenant_is_not_visible(): void
    {
        [$tenant, , , $branch, $token] = $this->scenario();

        $otherBranch = Branch::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/branches/'.$otherBranch->public_id)
            ->assertNotFound();
    }

    public function test_inactive_tenant_cannot_accept_orders(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);
        $branch = Branch::factory()->for($tenant)->create(['is_active' => true]);

        try {
            app(EnsureBranchAcceptsOrders::class)->handle($branch);
            $this->fail('An inactive tenant must reject order acceptance.');
        } catch (ApiException $exception) {
            $this->assertSame('TENANT_INACTIVE', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }
    }

    /**
     * @return array{Tenant, User, Role, Branch, string}
     */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'branch-admin', 'name' => 'Branch admin']);
        $branch = Branch::factory()->for($tenant)->create(['code' => 'BR-TEST']);

        $permissionIds = collect([PermissionCode::BranchesView, PermissionCode::BranchesManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap branch admin');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Branch test device');

        return [$tenant, $user, $role, $branch, $issued['token']->plainTextToken];
    }

    private function activeBranch(Tenant $tenant): Branch
    {
        return Branch::factory()->for($tenant)->create(['is_active' => true]);
    }
}
