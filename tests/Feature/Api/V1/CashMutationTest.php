<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Actions\IssueDeviceToken;
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

class CashMutationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expense_funding_deposit_and_inter_register_transfer_post_to_ledger(): void
    {
        [$tenant, $token, $branch, $register, $session, $secondRegister, $secondSession] = $this->scenario();

        $expense = $this->expense($tenant, $token, $branch, (string) Str::uuid(), 40000)
            ->assertCreated()
            ->assertJsonPath('data.approval_status', 'draft')
            ->assertJsonPath('data.cash_session_id', null);

        $this->assertDatabaseCount('cash_entries', 0);

        $this->transfer($tenant, $token, (string) Str::uuid(), [
            'kind' => 'owner_funding',
            'amount_rupiah' => 500000,
            'to_register_id' => $register->public_id,
            'to_cash_session_id' => $session->public_id,
            'reason' => 'Modal awal shift',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'owner_funding')
            ->assertJsonPath('data.status', 'confirmed');

        $this->transfer($tenant, $token, (string) Str::uuid(), [
            'kind' => 'bank_deposit',
            'amount_rupiah' => 100000,
            'from_register_id' => $register->public_id,
            'from_cash_session_id' => $session->public_id,
            'reason' => 'Setoran bank',
        ])->assertCreated();

        $this->transfer($tenant, $token, (string) Str::uuid(), [
            'kind' => 'inter_register',
            'amount_rupiah' => 50000,
            'from_register_id' => $register->public_id,
            'to_register_id' => $secondRegister->public_id,
            'from_cash_session_id' => $session->public_id,
            'to_cash_session_id' => $secondSession->public_id,
            'reason' => 'Pindah kas',
        ])->assertCreated();

        $this->assertDatabaseHas('cash_entries', ['tenant_id' => $tenant->id, 'kind' => 'transfer_in', 'amount_rupiah' => 500000]);
        $this->assertDatabaseHas('cash_entries', ['tenant_id' => $tenant->id, 'kind' => 'transfer_out', 'amount_rupiah' => -100000]);
        $this->assertSame(1, DB::table('cash_entries')->where('tenant_id', $tenant->id)->where('reference_type', 'cash_transfer')->where('kind', 'transfer_out')->where('amount_rupiah', -50000)->count());
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/expenses/'.$expense->json('data.expense_id').'/decision', [
                'decision' => 'approved',
                'cash_session_id' => $session->public_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.cash_session_id', $session->public_id);

        $this->assertDatabaseHas('cash_entries', [
            'tenant_id' => $tenant->id,
            'kind' => 'expense_out',
            'amount_rupiah' => -40000,
            'reference_type' => 'expense',
        ]);

        $rejected = $this->expense($tenant, $token, $branch, (string) Str::uuid(), 10000)->assertCreated();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/expenses/'.$rejected->json('data.expense_id').'/decision', [
                'decision' => 'rejected',
                'reason' => 'Tidak ada bukti',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'rejected');

        $this->assertDatabaseMissing('cash_entries', ['tenant_id' => $tenant->id, 'kind' => 'expense_out', 'amount_rupiah' => -10000]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$session->public_id.'/close', [
                'counted_closing_rupiah' => 510000,
            ])
            ->assertOk()
            ->assertJsonPath('data.ledger_rupiah', 310000)
            ->assertJsonPath('data.expected_rupiah', 510000)
            ->assertJsonPath('data.difference_rupiah', 0);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/cash-sessions/'.$secondSession->public_id.'/close', [
                'counted_closing_rupiah' => 250000,
            ])
            ->assertOk()
            ->assertJsonPath('data.ledger_rupiah', 50000)
            ->assertJsonPath('data.expected_rupiah', 250000);
    }

    public function test_expense_and_transfer_are_tenant_scoped_and_permission_checked(): void
    {
        [$tenant, $token, $branch, $register, $session] = $this->scenario();

        $otherBranch = Branch::factory()->create();
        $this->expense($tenant, $token, $otherBranch, (string) Str::uuid(), 1000)->assertNotFound();

        $otherRegister = CashRegister::factory()->create();
        $otherMembership = TenantMembership::factory()->for(Tenant::query()->findOrFail($otherRegister->tenant_id))->create(['status' => 'active']);
        $otherSession = CashSession::factory()->create([
            'tenant_id' => $otherRegister->tenant_id,
            'cash_register_id' => $otherRegister->id,
            'cashier_membership_id' => $otherMembership->id,
            'status' => 'open',
        ]);
        $this->transfer($tenant, $token, (string) Str::uuid(), [
            'kind' => 'owner_funding',
            'amount_rupiah' => 1000,
            'to_register_id' => $otherRegister->public_id,
            'to_cash_session_id' => $otherSession->public_id,
            'reason' => 'Register asing',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['to_register_id']]]);
    }

    private function expense(Tenant $tenant, string $token, Branch $branch, string $command, int $amount)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/expenses', [
                'client_command_id' => $command,
                'branch_id' => $branch->public_id,
                'category' => 'supplies',
                'description' => 'Pembelian deterjen',
                'amount_rupiah' => $amount,
                'occurred_at' => now()->toIso8601String(),
            ]);
    }

    private function transfer(Tenant $tenant, string $token, string $command, array $payload)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/cash-transfers', [
                'client_command_id' => $command,
                ...$payload,
            ]);
    }

    /** @return array{Tenant, string, Branch, CashRegister, CashSession, CashRegister, CashSession} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::CashView, PermissionCode::CashManage, PermissionCode::ExpensesManage, PermissionCode::ExpensesApprove])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cashier');

        $register = CashRegister::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'REG-A']);
        $session = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $register->id,
            'cashier_membership_id' => $membership->id,
            'opening_rupiah' => 200000,
            'status' => 'open',
        ]);
        $secondRegister = CashRegister::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'REG-B']);
        $secondSession = CashSession::factory()->create([
            'tenant_id' => $tenant->id,
            'cash_register_id' => $secondRegister->id,
            'cashier_membership_id' => $membership->id,
            'opening_rupiah' => 200000,
            'status' => 'open',
        ]);

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Cash mutation device')['token']->plainTextToken;

        return [$tenant, $token, $branch, $register, $session, $secondRegister, $secondSession];
    }
}
