<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeadlineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_calendar_is_configured_returned_and_audited(): void
    {
        [$tenant, $user, $token, $branch] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/branches/'.$branch->public_id.'/calendar')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonCount(0, 'data.weekly_hours');

        $response = $this->putCalendar($tenant, $token, $branch, [
            'reason' => 'Initial branch calendar',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.cutoff_time', '15:00')
            ->assertJsonPath('data.deadline_local_time', '20:00')
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(7, 'data.weekly_hours')
            ->assertJsonPath('data.weekly_hours.0.weekday', 0)
            ->assertJsonPath('data.weekly_hours.0.is_open', false)
            ->assertJsonPath('data.weekly_hours.1.is_open', true)
            ->assertJsonPath('data.holidays.0', '2026-11-05');

        $this->assertDatabaseCount('branch_calendars', 9);
        $this->assertSame(2, $branch->refresh()->version);

        $log = AuditLog::query()->where('action', AuditAction::BranchCalendarUpdated->value)->sole();
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame('branch', $log->subject_type);
        $this->assertSame($branch->public_id, $log->subject_public_id);
        $this->assertSame('Initial branch calendar', $log->reason);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/branches/'.$branch->public_id.'/calendar')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.version', 2);
    }

    public function test_preview_matches_cutoff_holiday_and_express_rules(): void
    {
        [$tenant, , $token, $branch] = $this->scenario();
        $this->putCalendar($tenant, $token, $branch)->assertOk();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create();

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T14:00:00+07:00', 'regular')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-04T13:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-04T20:00:00+07:00')
            ->assertJsonPath('data.start_date', '2026-11-02')
            ->assertJsonPath('data.cutoff_applied', false)
            ->assertJsonPath('data.working_days', 2)
            ->assertJsonPath('data.elapsed_hours', null);

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T16:00:00+07:00', 'regular')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-06T13:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-06T20:00:00+07:00')
            ->assertJsonPath('data.start_date', '2026-11-03')
            ->assertJsonPath('data.cutoff_applied', true);

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T16:00:00+07:00', 'express')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-04T13:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-04T20:00:00+07:00')
            ->assertJsonPath('data.working_days', 1);

        $this->preview($tenant, $token, $branch, $service, '2026-11-04T14:00:00+07:00', 'regular')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-07T13:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-07T20:00:00+07:00');

        $this->preview($tenant, $token, $branch, $service, '2026-11-04T14:00:00+07:00', 'express')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-06T13:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-06T20:00:00+07:00');
    }

    public function test_elapsed_service_ignores_working_days_cutoff_and_holidays(): void
    {
        [$tenant, , $token, $branch] = $this->scenario();
        $this->putCalendar($tenant, $token, $branch)->assertOk();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create([
            'code' => 'express-24',
            'sla_mode' => 'elapsed_hours',
            'regular_turnaround' => 24,
            'express_turnaround' => 6,
        ]);

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T16:00:00+07:00', 'regular')
            ->assertOk()
            ->assertJsonPath('data.mode', 'elapsed_hours')
            ->assertJsonPath('data.due_at', '2026-11-03T09:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-03T16:00:00+07:00')
            ->assertJsonPath('data.working_days', null)
            ->assertJsonPath('data.elapsed_hours', 24)
            ->assertJsonPath('data.cutoff_applied', false)
            ->assertJsonPath('data.start_date', null);

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T16:00:00+07:00', 'express')
            ->assertOk()
            ->assertJsonPath('data.due_at', '2026-11-02T15:00:00Z')
            ->assertJsonPath('data.due_at_local', '2026-11-02T22:00:00+07:00')
            ->assertJsonPath('data.elapsed_hours', 6);
    }

    public function test_calendar_validation_tenant_isolation_and_permissions(): void
    {
        [$tenant, $user, $token, $branch, $role] = $this->scenario();
        $otherBranch = Branch::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/branches/'.$otherBranch->public_id.'/calendar')
            ->assertNotFound();

        $this->putCalendar($tenant, $token, $branch, [
            'weekly_hours' => $this->weeklyHours(1, '09:00', '08:00'),
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['weekly_hours.1.close_time']]]);

        $this->putCalendar($tenant, $token, $branch, ['cutoff_time' => '21:00'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['cutoff_time']]]);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::BranchesView->value)->sole()->id],
            $user,
            'Downgrade to branch viewer',
        );

        $this->putCalendar($tenant, $token, $branch)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_preview_requires_a_configured_calendar(): void
    {
        [$tenant, , $token, $branch] = $this->scenario();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create();

        $this->preview($tenant, $token, $branch, $service, '2026-11-02T14:00:00+07:00', 'regular')
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.field_errors.branch_id.0',
                'The branch calendar must be configured before calculating a deadline.',
            );
    }

    private function putCalendar(
        Tenant $tenant,
        string $token,
        Branch $branch,
        array $overrides = [],
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->putJson('/api/v1/branches/'.$branch->public_id.'/calendar', [
                'weekly_hours' => $overrides['weekly_hours'] ?? $this->weeklyHours(),
                'holidays' => $overrides['holidays'] ?? ['2026-11-05'],
                'cutoff_time' => $overrides['cutoff_time'] ?? '15:00',
                'deadline_local_time' => $overrides['deadline_local_time'] ?? '20:00',
                'reason' => $overrides['reason'] ?? 'Test calendar',
            ]);
    }

    /**
     * @return list<array{weekday: int, is_open: bool, open_time?: string, close_time?: string}>
     */
    private function weeklyHours(?int $brokenWeekday = null, string $open = '08:00', string $close = '20:00'): array
    {
        return array_map(function (int $weekday) use ($brokenWeekday, $open, $close): array {
            if ($weekday === 0) {
                return ['weekday' => 0, 'is_open' => false];
            }

            if ($weekday === $brokenWeekday) {
                return ['weekday' => $weekday, 'is_open' => true, 'open_time' => $open, 'close_time' => $close];
            }

            return ['weekday' => $weekday, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'];
        }, range(0, 6));
    }

    private function preview(
        Tenant $tenant,
        string $token,
        Branch $branch,
        Service $service,
        string $acceptedAt,
        string $tier,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/deadlines/preview', [
                'branch_id' => $branch->public_id,
                'service_id' => $service->public_id,
                'tier' => $tier,
                'accepted_at' => $acceptedAt,
            ]);
    }

    /** @return array{Tenant, User, string, Branch, Role} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'branch-admin', 'name' => 'Branch admin']);
        $branch = Branch::factory()->for($tenant)->create();

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

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Deadline test device');

        return [$tenant, $user, $issued['token']->plainTextToken, $branch, $role];
    }
}
