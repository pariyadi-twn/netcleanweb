<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Models\BranchCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscountApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_standard_discount_is_auto_approved_and_can_be_accepted(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $requesterToken, , $branch, $service] = $this->scenario();

        $quote = $this->quote($tenant, $requesterToken, $branch, $service, 10)
            ->assertCreated()
            ->assertJsonPath('data.discount_approval.status', 'approved')
            ->assertJsonPath('data.discount_approval.percent', 10);

        $this->accept($tenant, $requesterToken, $quote->json('data.id'), $branch, $service, 10)
            ->assertCreated();
    }

    public function test_discount_above_ten_requires_a_different_override_actor_before_acceptance(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , $requesterToken, $approverToken, $branch, $service] = $this->scenario();
        $quote = $this->quote($tenant, $requesterToken, $branch, $service, 20)
            ->assertCreated()
            ->assertJsonPath('data.discount_approval.status', 'pending');

        $quoteId = $quote->json('data.id');

        $this->accept($tenant, $requesterToken, $quoteId, $branch, $service, 20)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DISCOUNT_APPROVAL_REQUIRED');

        $this->decide($tenant, $requesterToken, $quoteId, 'approved', 'Self approval')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->decide($tenant, $approverToken, $quoteId, 'approved', 'Owner approval')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.percent', 20);

        $this->app['auth']->forgetGuards();
        $this->accept($tenant, $requesterToken, $quoteId, $branch, $service, 20)
            ->assertCreated();
    }

    public function test_actor_without_discount_permission_cannot_apply_discount(): void
    {
        [$tenant, $user, $requesterToken, , $branch, $service, $requesterRole] = $this->scenario();

        app(SyncRolePermissions::class)->handle(
            $requesterRole,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id,
                Permission::query()->where('code', PermissionCode::OrdersManage->value)->sole()->id],
            $user,
            'Remove discount permission',
        );

        $this->quote($tenant, $requesterToken, $branch, $service, 5)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DISCOUNT_NOT_ALLOWED');
    }

    public function test_override_actor_cannot_approve_a_quote_they_requested(): void
    {
        $this->travelTo('2026-11-02 07:00:00');
        [$tenant, , , $approverToken, $branch, $service] = $this->scenario();
        $quoteId = $this->quote($tenant, $approverToken, $branch, $service, 20)
            ->assertCreated()
            ->assertJsonPath('data.discount_approval.status', 'pending')
            ->json('data.id');

        $this->decide($tenant, $approverToken, $quoteId, 'approved', 'Self approval')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DISCOUNT_APPROVAL_CONFLICT');
    }

    private function quote(
        Tenant $tenant,
        string $token,
        Branch $branch,
        Service $service,
        int $percent,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/quotes', [
                'branch_id' => $branch->public_id,
                'fulfillment_method' => 'pickup',
                'service_discount_percent' => $percent,
                'discount_reason' => $percent > 0 ? 'Test discount' : null,
                'lines' => [
                    ['service_id' => $service->public_id, 'measured_quantity' => '2.3'],
                ],
            ]);
    }

    private function accept(
        Tenant $tenant,
        string $token,
        string $quoteId,
        Branch $branch,
        Service $service,
        int $percent,
    ): TestResponse {
        $command = (string) Str::uuid();

        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/orders', [
                'client_command_id' => $command,
                'quote_id' => $quoteId,
                'branch_id' => $branch->public_id,
                'fulfillment_method' => 'pickup',
                'service_discount_percent' => $percent,
                'discount_reason' => $percent > 0 ? 'Test discount' : null,
                'lines' => [
                    ['service_id' => $service->public_id, 'measured_quantity' => '2.3'],
                ],
            ]);
    }

    private function decide(
        Tenant $tenant,
        string $token,
        string $quoteId,
        string $decision,
        string $reason,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/quotes/'.$quoteId.'/discount-approval', [
                'decision' => $decision,
                'reason' => $reason,
            ]);
    }

    /** @return array{Tenant, User, string, string, Branch, Service, Role} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $requesterMembership = TenantMembership::factory()->for($tenant)->for($requester)->create(['status' => 'active']);
        $approverMembership = TenantMembership::factory()->for($tenant)->for($approver)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create(['code' => 'JBI01']);
        $requesterRole = Role::factory()->for($tenant)->create(['code' => 'supervisor', 'name' => 'Supervisor']);
        $approverRole = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);
        $requesterPermissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::DiscountsStandard])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        $approverPermissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage, PermissionCode::DiscountsOverride])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);

        DB::table('role_permissions')->insert($requesterPermissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $requesterRole->id, 'permission_id' => $id,
        ])->all());
        DB::table('role_permissions')->insert($approverPermissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $approverRole->id, 'permission_id' => $id,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($requesterMembership, [$requesterRole->id], [$branch->id], [], $requester, 'Bootstrap supervisor');
        app(UpdateMembershipAssignments::class)->handle($approverMembership, [$approverRole->id], [$branch->id], [], $approver, 'Bootstrap owner');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard']);
        $service = Service::factory()->forWorkflow($workflow)->create([
            'code' => 'kiloan',
            'unit' => 'kg',
            'minimum_quantity' => '3.000',
            'quantity_step' => '0.100',
            'is_active' => true,
        ]);
        $book = PriceBook::factory()->for($tenant)->create();
        PriceEntry::factory()->forPriceBook($book, $service)->create([
            'unit_price_rupiah' => 8000,
            'effective_from' => CarbonImmutable::parse('2026-01-01 00:00:00', 'Asia/Jakarta')->utc(),
            'effective_until' => null,
        ]);

        foreach (range(0, 6) as $weekday) {
            BranchCalendar::factory()->forBranch($branch)->create([
                'weekday' => $weekday,
                'rules' => $weekday === 0
                    ? ['is_open' => false]
                    : ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            ]);
        }
        BranchCalendar::factory()->forBranch($branch)->cutoff('15:00', '20:00')->create();
        BranchCalendar::factory()->forBranch($branch)->holiday('2026-11-05')->create();

        $requesterToken = app(IssueDeviceToken::class)->handle($requester, (string) Str::uuid(), 'Requester device')['token']->plainTextToken;
        $approverToken = app(IssueDeviceToken::class)->handle($approver, (string) Str::uuid(), 'Approver device')['token']->plainTextToken;

        return [$tenant, $requester, $requesterToken, $approverToken, $branch, $service, $requesterRole];
    }
}
