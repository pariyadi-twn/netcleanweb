<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assignment_changes_record_actor_subject_reason_and_correlation(): void
    {
        Context::add('correlation_id', 'audit-correlation-1');

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($actor)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create();
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();

        app(UpdateMembershipAssignments::class)->handle(
            $membership,
            [$role->id],
            [$branch->id],
            [$location->id],
            actor: $actor,
            reason: 'Initial owner assignment',
        );

        $log = AuditLog::query()->sole();

        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame(AuditAction::MembershipAssignmentsUpdated, $log->action);
        $this->assertSame(AuditRecorder::SUBJECT_TENANT_MEMBERSHIP, $log->subject_type);
        $this->assertSame($membership->public_id, $log->subject_public_id);
        $this->assertSame('audit-correlation-1', $log->correlation_id);
        $this->assertSame('Initial owner assignment', $log->reason);
        $this->assertSame([$role->id], $log->redacted_changes['role_ids']);
        $this->assertSame([$branch->id], $log->redacted_changes['branch_ids']);
        $this->assertSame([$location->id], $log->redacted_changes['location_ids']);
    }

    public function test_role_permission_changes_are_audited(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();
        $role = Role::factory()->for($tenant)->create();
        $permission = Permission::factory()->create();

        app(SyncRolePermissions::class)->handle($role, [$permission->id], $actor, 'Permission review');

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::RolePermissionsUpdated, $log->action);
        $this->assertSame(AuditRecorder::SUBJECT_ROLE, $log->subject_type);
        $this->assertSame($role->public_id, $log->subject_public_id);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame([$permission->id], $log->redacted_changes['permission_ids']);
        $this->assertSame('Permission review', $log->reason);
    }

    public function test_sensitive_change_values_are_redacted_recursively(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();

        $log = app(AuditRecorder::class)->record(
            tenantId: $tenant->id,
            action: AuditAction::MembershipAssignmentsUpdated,
            subjectType: AuditRecorder::SUBJECT_TENANT_MEMBERSHIP,
            subjectPublicId: null,
            changes: [
                'role_ids' => [10, 11],
                'password' => 'plain-password',
                'nested' => [
                    'token' => 'plain-token',
                    'mfa_secret' => 'plain-secret',
                    'safe' => 'visible',
                ],
                'reason_code' => 'OWNER_CHANGE',
            ],
            actor: $actor,
        );

        $this->assertSame([
            'role_ids' => [10, 11],
            'nested' => ['safe' => 'visible'],
            'reason_code' => 'OWNER_CHANGE',
        ], $log->redacted_changes);

        $encoded = json_encode($log->redacted_changes, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('plain-password', $encoded);
        $this->assertStringNotContainsString('plain-token', $encoded);
        $this->assertStringNotContainsString('plain-secret', $encoded);
    }

    public function test_unknown_audit_subject_type_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(AuditRecorder::class)->record(
            tenantId: $tenant->id,
            action: AuditAction::MembershipAssignmentsUpdated,
            subjectType: 'arbitrary_class',
            subjectPublicId: null,
        );
    }

    public function test_audit_log_records_are_created_with_database_timestamps(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $log = app(AuditRecorder::class)->record(
            tenantId: $tenant->id,
            action: AuditAction::RolePermissionsUpdated,
            subjectType: AuditRecorder::SUBJECT_ROLE,
            subjectPublicId: null,
            actor: $user,
        );

        $this->assertNotNull($log->created_at);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $user->id,
            'action' => AuditAction::RolePermissionsUpdated->value,
        ]);
        $this->assertSame(1, DB::table('audit_logs')->count());
    }
}
