<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_lists_tenants_with_minimal_pii_and_manages_plans(): void
    {
        [$tenant, $owner, $platformToken, $branch] = $this->scenario();
        $customer = new Customer(['name' => 'Rahasia', 'normalized_phone' => '081200000001']);
        $customer->tenant_id = $tenant->id;
        $customer->save();

        $overview = $this->withToken($platformToken)
            ->getJson('/api/v1/platform/tenants/'.$tenant->public_id)
            ->assertOk()
            ->assertJsonPath('data.tenant.tenant_id', $tenant->public_id)
            ->assertJsonPath('data.counts.customers', 1)
            ->assertJsonMissingPath('data.tenant.phone')
            ->assertJsonMissingPath('data.tenant.customer_names');

        $this->assertNotNull($overview->json('data.usage'));

        $tenantList = $this->withToken($platformToken)
            ->getJson('/api/v1/platform/tenants')
            ->assertOk();
        $this->assertSame($tenant->public_id, $tenantList->json('data.0.tenant_id'));
        $this->assertArrayNotHasKey('phone', $tenantList->json('data.0'));

        $plan = $this->withToken($platformToken)
            ->postJson('/api/v1/platform/plans', [
                'code' => 'growth',
                'revision' => 1,
                'name' => 'Growth',
                'base_price_rupiah' => 250000,
                'features' => ['limits' => ['staff' => 10], 'features' => ['exports' => true]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'growth');

        $this->assertSame(250000, $plan->json('data.base_price_rupiah'));

        $fresh = Tenant::factory()->create();
        $subscription = $this->withToken($platformToken)
            ->postJson('/api/v1/platform/tenants/'.$fresh->public_id.'/subscription', [
                'plan_code' => 'growth',
                'plan_revision' => 1,
                'status' => 'active',
                'period_start' => now()->startOfMonth()->toIso8601String(),
                'period_end' => now()->endOfMonth()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.plan_code', 'growth')
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($subscription->json('data.period_end'));
    }

    public function test_deletion_request_requires_verification_and_anonymizes_without_removing_ledger(): void
    {
        [$tenant, $owner, $platformToken, $branch, $ownerToken, $role] = $this->scenario();
        $customer = new Customer(['name' => 'Rahasia', 'normalized_phone' => '081200000002', 'email' => 'rahasia@example.com']);
        $customer->tenant_id = $tenant->id;
        $customer->save();

        $request = $this->withToken($ownerToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/data-requests', [
                'customer_id' => $customer->public_id,
                'reason' => 'Permintaan penghapusan pelanggan',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested');

        $requestId = $request->json('data.data_request_id');

        $this->app['auth']->forgetGuards();
        $this->withToken($platformToken)
            ->postJson('/api/v1/platform/data-requests/'.$requestId.'/verify', [
                'verification_method' => 'owner_video_call',
                'reason' => 'Identitas diverifikasi',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->app['auth']->forgetGuards();
        $this->withToken($platformToken)
            ->postJson('/api/v1/platform/data-requests/'.$requestId.'/process')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.result_summary.customer_id', $customer->public_id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Deleted Customer',
            'normalized_phone' => null,
            'email' => null,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('data_deletion_tombstones', [
            'tenant_id' => $tenant->id,
            'subject_public_id' => $customer->public_id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'data_request.completed']);

        $customer->forceFill(['name' => 'Restored PII', 'normalized_phone' => '081200000099', 'is_active' => true])->save();
        $this->artisan('data:reapply-deletions', ['--tenant' => $tenant->public_id])->assertSuccessful();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Deleted Customer', 'normalized_phone' => null]);
    }

    public function test_non_platform_and_unpermitted_users_cannot_use_admin_or_data_requests(): void
    {
        [$tenant, $owner, $platformToken, $branch, $ownerToken, $role] = $this->scenario();
        $normalToken = $this->tokenFor(User::factory()->create());

        $this->app['auth']->forgetGuards();
        $this->withToken($normalToken)
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::BranchesView->value)->sole()->id],
            $owner,
            'Remove data request permission',
        );

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/data-requests', [
                'customer_id' => (string) Str::uuid(),
                'reason' => 'No permission',
            ])
            ->assertForbidden();
    }

    /** @return array{Tenant, User, string, Branch, string, Role} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($owner)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::DataRequestsManage, PermissionCode::BranchesView])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $owner, 'Bootstrap lifecycle owner');

        $ownerToken = $this->tokenFor($owner);
        $platformToken = $this->tokenFor(User::factory()->platformAdmin()->create());

        return [$tenant, $owner, $platformToken, $branch, $ownerToken, $role];
    }

    private function tokenFor(User $user): string
    {
        return app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Administration device')['token']->plainTextToken;
    }
}
