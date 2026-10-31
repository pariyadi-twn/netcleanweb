<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
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
use Database\Seeders\IdentityPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_provisions_tenant_branch_and_owner_membership_idempotently(): void
    {
        $this->seed(IdentityPermissionSeeder::class);

        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId);

        $first = $this->send($token, $clientCommandId, $payload);

        $first->assertCreated()
            ->assertJsonPath('data.tenant_name', 'Laundry Bersih')
            ->assertJsonPath('data.branch_name', 'Cabang Utama')
            ->assertJsonPath('meta.idempotent_replay', false);

        $tenantId = $first->json('data.tenant_id');
        $branchId = $first->json('data.branch_id');
        $membershipId = $first->json('data.membership_id');

        $this->assertTrue(Str::isUuid($tenantId));
        $this->assertTrue(Str::isUuid($branchId));
        $this->assertTrue(Str::isUuid($membershipId));

        $tenant = Tenant::query()->where('public_id', $tenantId)->sole();
        $branch = Branch::query()->where('public_id', $branchId)->sole();
        $membership = TenantMembership::query()->where('public_id', $membershipId)->sole();
        $ownerRole = Role::query()->where('tenant_id', $tenant->id)->where('code', RoleCode::Owner->value)->sole();

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('Asia/Jakarta', $tenant->timezone);
        $this->assertSame('BR-MAIN', $branch->code);
        $this->assertSame('Asia/Makassar', $branch->timezone);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame(count(Permission::all()), $ownerRole->permissions()->count());
        $this->assertSame(1, $membership->roles()->where('roles.id', $ownerRole->id)->count());
        $this->assertSame(1, $membership->branches()->where('branches.id', $branch->id)->count());
        $this->assertSame(1, ProvisioningRequest::query()->where('status', ProvisioningStatus::Completed->value)->count());
        $this->assertSame(1, Tenant::query()->count());

        $replay = $this->send($token, $clientCommandId, $payload);

        $replay->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true)
            ->assertJsonPath('data.tenant_id', $tenantId)
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.membership_id', $membershipId);

        $this->assertSame(1, Tenant::query()->count());
        $this->assertSame(1, ProvisioningRequest::query()->count());
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $this->seed(IdentityPermissionSeeder::class);

        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId);

        $this->send($token, $clientCommandId, $payload)->assertCreated();

        $different = $payload;
        $different['organization_name'] = 'Another Laundry';

        $this->send($token, $clientCommandId, $different)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_same_command_key_is_scoped_per_owner_identity(): void
    {
        $this->seed(IdentityPermissionSeeder::class);

        $first = User::factory()->create();
        $second = User::factory()->create();
        $clientCommandId = (string) Str::uuid();

        $this->send($this->tokenFor($first), $clientCommandId, $this->payload($clientCommandId))->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->send($this->tokenFor($second), $clientCommandId, $this->payload($clientCommandId))->assertCreated();

        $this->assertSame(2, Tenant::query()->count());
        $this->assertSame(2, ProvisioningRequest::query()->count());
    }

    public function test_header_mismatch_and_unauthenticated_requests_are_rejected(): void
    {
        $this->seed(IdentityPermissionSeeder::class);

        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $clientCommandId = (string) Str::uuid();

        $this->send($token, (string) Str::uuid(), $this->payload($clientCommandId))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['client_command_id']]]);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Idempotency-Key', $clientCommandId)
            ->postJson('/api/v1/provisioning/tenants', $this->payload($clientCommandId))
            ->assertUnauthorized();

        $this->assertSame(0, Tenant::query()->count());
    }

    private function tokenFor(User $user): string
    {
        return app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Provisioning test device')['token']->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $clientCommandId): array
    {
        return [
            'client_command_id' => $clientCommandId,
            'organization_name' => 'Laundry Bersih',
            'timezone' => 'Asia/Jakarta',
            'branch_name' => 'Cabang Utama',
            'branch_code' => 'BR-MAIN',
            'branch_timezone' => 'Asia/Makassar',
            'branch_address' => 'Jl. Contoh 1',
            'branch_phone' => '081234567890',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $token, string $idempotencyKey, array $payload): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/provisioning/tenants', $payload);
    }
}
