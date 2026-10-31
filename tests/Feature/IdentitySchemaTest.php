<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\ReparentLocation;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Closure;
use Database\Seeders\IdentityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class IdentitySchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_one_identity_can_join_multiple_tenants_with_separate_public_uuids(): void
    {
        $user = User::factory()->create();
        $first = TenantMembership::factory()->for($user)->create();
        $second = TenantMembership::factory()->for($user)->create();

        $this->assertSame(2, $user->memberships()->count());
        $this->assertNotSame($first->tenant_id, $second->tenant_id);
        $this->assertTrue(Str::isUuid($user->public_id));
        $this->assertTrue(Str::isUuid($first->public_id));
        $this->assertIsInt($first->id);
        $this->assertSame($first->public_id, $first->getRouteKey());
    }

    public function test_duplicate_membership_for_the_same_identity_and_tenant_is_rejected(): void
    {
        $membership = TenantMembership::factory()->create();

        $this->assertSqlState('23505', fn () => TenantMembership::factory()->create(['tenant_id' => $membership->tenant_id, 'user_id' => $membership->user_id]));
    }

    /** @return array<string, array{string}> */
    public static function crossTenantRelations(): array
    {
        return [
            'location branch' => ['location_branch'],
            'location parent' => ['location_parent'],
            'branch assignment' => ['branch_assignments'],
            'location assignment' => ['location_assignments'],
            'membership role' => ['membership_roles'],
            'role permission' => ['role_permissions'],
        ];
    }

    #[DataProvider('crossTenantRelations')]
    public function test_composite_foreign_keys_reject_cross_tenant_relations(string $relation): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $member = TenantMembership::factory()->for($tenant)->create();
        $otherBranch = Branch::factory()->for($other)->create();
        $otherLocation = Location::factory()->for($other)->create();
        $otherRole = Role::factory()->for($other)->create();
        $permission = Permission::factory()->create();
        $write = match ($relation) {
            'location_branch' => fn () => Location::factory()->for($tenant)->create(['branch_id' => $otherBranch->id]),
            'location_parent' => fn () => Location::factory()->for($tenant)->create(['parent_location_id' => $otherLocation->id]),
            'branch_assignments' => fn () => DB::table($relation)->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'branch_id' => $otherBranch->id]),
            'location_assignments' => fn () => DB::table($relation)->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'location_id' => $otherLocation->id]),
            'membership_roles' => fn () => DB::table($relation)->insert(['tenant_id' => $tenant->id, 'membership_id' => $member->id, 'role_id' => $otherRole->id]),
            'role_permissions' => fn () => DB::table($relation)->insert(['tenant_id' => $tenant->id, 'role_id' => $otherRole->id, 'permission_id' => $permission->id]),
        };

        $this->assertSqlState('23503', $write);
    }

    public function test_role_permissions_and_branch_assignments_resolve_in_the_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->create();
        $branch = Branch::factory()->for($tenant)->create();
        $role = Role::factory()->for($tenant)->create();
        $permission = Permission::factory()->create();
        $membership->roles()->attach($role, ['tenant_id' => $tenant->id]);
        $membership->branches()->attach($branch, ['tenant_id' => $tenant->id]);
        $role->permissions()->attach($permission, ['tenant_id' => $tenant->id]);

        $this->assertSame($role->id, $membership->roles()->sole()->id);
        $this->assertSame($branch->id, $membership->branches()->sole()->id);
        $this->assertSame($permission->id, $membership->roles()->sole()->permissions()->sole()->id);
    }

    public function test_branch_codes_are_unique_within_a_tenant_but_can_be_reused_by_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Branch::factory()->for($tenant)->create(['code' => 'JBI01']);
        Branch::factory()->create(['code' => 'JBI01']);

        $this->assertSqlState('23505', fn () => Branch::factory()->for($tenant)->create(['code' => 'JBI01']));
    }

    public function test_referenced_tenant_cannot_be_deleted(): void
    {
        $branch = Branch::factory()->create();

        $this->assertSqlState('23001', fn () => $branch->tenant->delete());
    }

    public function test_tenant_id_and_public_id_cannot_be_changed_by_payload_or_model_update(): void
    {
        $branch = Branch::factory()->create();
        $tenantId = $branch->tenant_id;
        $publicId = $branch->public_id;
        $otherTenant = Tenant::factory()->create();
        $branch->fill(['tenant_id' => $otherTenant->id, 'public_id' => (string) Str::uuid()])->save();

        $this->assertSame($tenantId, $branch->refresh()->tenant_id);
        $this->assertSame($publicId, $branch->public_id);

        try {
            $branch->tenant_id = $otherTenant->id;
            $branch->save();
            $this->fail('A persisted branch cannot be moved into another tenant.');
        } catch (LogicException) {
            $this->assertSame($tenantId, $branch->refresh()->tenant_id);
        }
    }

    public function test_location_can_be_reparented_with_version_control(): void
    {
        $tenant = Tenant::factory()->create();
        $parent = Location::factory()->for($tenant)->create();
        $child = Location::factory()->for($tenant)->create();

        $result = app(ReparentLocation::class)->handle($child, $parent, 1);

        $this->assertSame($parent->id, $result->parent_location_id);
        $this->assertSame(2, $result->version);

        try {
            app(ReparentLocation::class)->handle($child, null, 1);
            $this->fail('An outdated location version must not overwrite the parent.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame($parent->id, $child->refresh()->parent_location_id);
        }
    }

    public function test_location_hierarchy_rejects_cycles_without_changing_parent(): void
    {
        $tenant = Tenant::factory()->create();
        $root = Location::factory()->for($tenant)->create();
        $child = Location::factory()->for($tenant)->create(['parent_location_id' => $root->id]);

        try {
            app(ReparentLocation::class)->handle($root, $child, 1);
            $this->fail('A location hierarchy cannot contain a cycle.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Hierarki lokasi tidak boleh membentuk siklus.'], $exception->errors()['parent_location_id']);
            $this->assertNull($root->refresh()->parent_location_id);
            $this->assertSame(1, $root->version);
        }
    }

    public function test_location_hierarchy_rejects_a_parent_in_another_tenant(): void
    {
        $location = Location::factory()->create();
        $otherParent = Location::factory()->create();

        try {
            app(ReparentLocation::class)->handle($location, $otherParent, 1);
            $this->fail('A parent from another tenant must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Lokasi induk harus berada dalam organisasi yang sama.'], $exception->errors()['parent_location_id']);
            $this->assertNull($location->refresh()->parent_location_id);
        }
    }

    public function test_permission_seeding_is_repeatable_without_replacing_public_ids(): void
    {
        $this->seed(IdentityPermissionSeeder::class);
        $firstIds = Permission::query()->orderBy('code')->pluck('public_id')->all();

        $this->seed(IdentityPermissionSeeder::class);

        $this->assertSame(8, Permission::query()->count());
        $this->assertSame($firstIds, Permission::query()->orderBy('code')->pluck('public_id')->all());
    }

    public function test_legacy_users_receive_public_ids_without_losing_identity_password_or_utc_time(): void
    {
        $migration = require database_path('migrations/2026_10_31_023706_add_public_identity_to_users_table.php');
        $migration->down();
        $password = Hash::make('synthetic-legacy-password');
        $id = DB::table('users')->insertGetId(['name' => 'Legacy User', 'email' => 'legacy@example.test', 'password' => $password, 'created_at' => '2026-10-01 09:15:00', 'updated_at' => '2026-10-01 09:15:00']);

        $migration->up();
        $user = User::query()->findOrFail($id);

        $this->assertSame('legacy@example.test', $user->email);
        $this->assertSame($password, $user->getRawOriginal('password'));
        $this->assertTrue(Str::isUuid($user->public_id));
        $this->assertSame('2026-10-01T09:15:00+00:00', $user->created_at->toIso8601String());
    }

    private function assertSqlState(string $expected, Closure $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('The database accepted an invalid identity relationship.');
        } catch (QueryException $exception) {
            $this->assertSame($expected, $exception->errorInfo[0]);
        }
    }
}
