<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Cash\Models\CashEntry;
use App\Modules\Cash\Models\CashRegister;
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

class CashSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_register_shift_ledger_close_and_reopen_are_traceable(): void
    {
        [$tenant, $token, $branch, $role, $user] = $this->scenario();

        $register = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/branches/'.$branch->public_id.'/cash-registers', ['code' => 'REG-1'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'REG-1');

        $registerId = $register->json('data.id');

        $session = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-registers/'.$registerId.'/sessions', ['opening_rupiah' => 200000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.expected_rupiah', 200000)
            ->assertJsonPath('data.ledger_rupiah', 0);

        $sessionId = $session->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-registers/'.$registerId.'/sessions', ['opening_rupiah' => 100000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CASH_SESSION_OPEN');

        CashEntry::factory()->create([
            'cash_session_id' => DB::table('cash_sessions')->where('public_id', $sessionId)->value('id'),
            'kind' => 'payment_in',
            'amount_rupiah' => 500000,
        ]);
        CashEntry::factory()->create([
            'cash_session_id' => DB::table('cash_sessions')->where('public_id', $sessionId)->value('id'),
            'kind' => 'expense_out',
            'amount_rupiah' => -50000,
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$sessionId.'/close', [
                'counted_closing_rupiah' => 648000,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['reason']]]);

        $closed = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$sessionId.'/close', [
                'counted_closing_rupiah' => 648000,
                'reason' => 'Kurang Rp2.000',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.ledger_rupiah', 450000)
            ->assertJsonPath('data.expected_rupiah', 650000)
            ->assertJsonPath('data.counted_closing_rupiah', 648000)
            ->assertJsonPath('data.difference_rupiah', -2000);

        $this->assertDatabaseHas('cash_session_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'closed',
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$sessionId.'/reopen', ['reason' => 'Koreksi setoran'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.counted_closing_rupiah', null);

        $events = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/cash-sessions/'.$sessionId.'/events')
            ->assertOk();

        $types = collect($events->json('data'))->pluck('event_type')->all();
        $this->assertSame(['opened', 'closed', 'reopened'], $types);
        $this->assertSame(2000, abs($events->json('data.1.payload.difference_rupiah')));
        $this->assertSame('Kurang Rp2.000', $events->json('data.1.reason'));
        $this->assertSame('Koreksi setoran', $events->json('data.2.reason'));

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::CashView->value)->sole()->id],
            $user,
            'Downgrade to cash viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/cash-sessions/'.$sessionId)
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$sessionId.'/reopen', ['reason' => 'Denied'])
            ->assertForbidden();
    }

    public function test_reopen_requires_closed_session_and_register_is_tenant_scoped(): void
    {
        [$tenant, $token, $branch] = $this->scenario();
        $register = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/branches/'.$branch->public_id.'/cash-registers', ['code' => 'REG-2'])
            ->assertCreated()
            ->json('data.id');
        $session = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-registers/'.$register.'/sessions', ['opening_rupiah' => 100000])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$session.'/reopen', ['reason' => 'Masih buka'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CASH_SESSION_OPEN');

        $otherRegister = CashRegister::factory()->create();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-registers/'.$otherRegister->public_id.'/sessions', ['opening_rupiah' => 1])
            ->assertNotFound();
    }

    /** @return array{Tenant, string, Branch, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cash-admin', 'name' => 'Cash admin']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::CashView, PermissionCode::CashManage, PermissionCode::CashReopen])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cash admin');

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Cash device')['token']->plainTextToken;

        return [$tenant, $token, $branch, $role, $user];
    }
}
