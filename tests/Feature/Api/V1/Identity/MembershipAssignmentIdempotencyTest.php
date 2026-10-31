<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Enums\CommandReceiptStatus;
use App\Modules\Operations\Models\CommandReceipt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MembershipAssignmentIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_same_key_and_payload_replays_the_same_resource_without_duplicate_effects(): void
    {
        [$tenant, $actor, $actorMembership, $actorRole, $target, $role, $branch, $location, $token] = $this->scenario();

        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId, $role, $branch, $location);

        $first = $this->send($tenant, $target, $token, $clientCommandId, $payload);

        $first->assertOk()
            ->assertJsonPath('data.membership_id', $target->public_id)
            ->assertJsonPath('data.permission_version', 2)
            ->assertJsonPath('data.role_ids', [$role->public_id])
            ->assertJsonPath('data.branch_ids', [$branch->public_id])
            ->assertJsonPath('data.location_ids', [$location->public_id])
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseCount('command_receipts', 1);
        $receipt = CommandReceipt::query()->sole();
        $this->assertSame(CommandReceiptStatus::Completed, $receipt->status);
        $this->assertSame($clientCommandId, $receipt->client_command_id);
        $this->assertSame('membership.assignments.update', $receipt->command_type);
        $this->assertSame(64, strlen($receipt->request_hash));
        $this->assertSame($target->public_id, $receipt->result_public_id);
        $this->assertSame($actorMembership->id, $receipt->actor_membership_id);
        $this->assertSame(1, $target->roles()->count());
        $this->assertSame(1, AuditLog::query()->where('subject_public_id', $target->public_id)->count());

        $replay = $this->send($tenant, $target, $token, $clientCommandId, $payload);

        $replay->assertOk()
            ->assertJsonPath('data.membership_id', $target->public_id)
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertDatabaseCount('command_receipts', 1);
        $this->assertSame(1, $target->roles()->count());
        $this->assertSame(1, AuditLog::query()->where('subject_public_id', $target->public_id)->count());

        $this->assertNotSame($actor->id, $target->user_id);
        $this->assertSame($actorRole->id, $actorMembership->roles()->sole()->id);
    }

    public function test_same_key_with_different_payload_returns_409_without_reapplying_the_command(): void
    {
        [$tenant, , , , $target, $role, $branch, $location, $token] = $this->scenario();

        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId, $role, $branch, $location);

        $this->send($tenant, $target, $token, $clientCommandId, $payload)->assertOk();

        $different = $payload;
        $different['reason'] = 'Different payload for the same key';

        $this->send($tenant, $target, $token, $clientCommandId, $different)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        $this->assertDatabaseCount('command_receipts', 1);
        $this->assertSame(1, $target->roles()->count());
        $this->assertSame(1, AuditLog::query()->where('subject_public_id', $target->public_id)->count());
    }

    public function test_idempotency_header_must_match_the_payload_command_id(): void
    {
        [$tenant, , , , $target, $role, $branch, $location, $token] = $this->scenario();

        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId, $role, $branch, $location);

        $this->send($tenant, $target, $token, (string) Str::uuid(), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['field_errors' => ['client_command_id']]]);

        $this->assertDatabaseCount('command_receipts', 0);
        $this->assertSame(0, $target->roles()->count());
    }

    public function test_replay_reauthorizes_the_actor_after_permission_loss(): void
    {
        [$tenant, $actor, $actorMembership, $actorRole, $target, $role, $branch, $location, $token] = $this->scenario();

        $clientCommandId = (string) Str::uuid();
        $payload = $this->payload($clientCommandId, $role, $branch, $location);

        $this->send($tenant, $target, $token, $clientCommandId, $payload)->assertOk();

        app(SyncRolePermissions::class)->handle($actorRole, [], $actor, 'Revoke assignment management');

        $this->send($tenant, $target, $token, $clientCommandId, $payload)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertDatabaseCount('command_receipts', 1);
        $this->assertSame(1, $target->roles()->count());
    }

    /**
     * @return array{Tenant, User, TenantMembership, Role, TenantMembership, Role, Branch, Location, string}
     */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();
        $actorMembership = TenantMembership::factory()->for($tenant)->for($actor)->create(['status' => 'active']);
        $actorRole = Role::factory()->for($tenant)->create(['code' => 'membership-admin', 'name' => 'Membership admin']);

        $managePermission = Permission::query()->firstOrCreate(
            ['code' => PermissionCode::MembershipsManage->value],
            ['description' => PermissionCode::MembershipsManage->name],
        );

        DB::table('role_permissions')->insert([
            'tenant_id' => $tenant->id,
            'role_id' => $actorRole->id,
            'permission_id' => $managePermission->id,
        ]);

        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();
        $target = TenantMembership::factory()->for($tenant)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);

        app(UpdateMembershipAssignments::class)->handle(
            $actorMembership,
            [$actorRole->id],
            [$branch->id],
            [$location->id],
            $actor,
            'Bootstrap membership admin',
        );

        $issued = app(IssueDeviceToken::class)->handle($actor, (string) Str::uuid(), 'Idempotency test device');

        return [$tenant, $actor, $actorMembership, $actorRole, $target, $role, $branch, $location, $issued['token']->plainTextToken];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $clientCommandId, Role $role, Branch $branch, Location $location): array
    {
        return [
            'client_command_id' => $clientCommandId,
            'role_ids' => [$role->public_id],
            'branch_ids' => [$branch->public_id],
            'location_ids' => [$location->public_id],
            'reason' => 'Assign cashier',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(Tenant $tenant, TenantMembership $target, string $token, string $idempotencyKey, array $payload): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/memberships/'.$target->public_id.'/assignments', $payload);
    }
}
