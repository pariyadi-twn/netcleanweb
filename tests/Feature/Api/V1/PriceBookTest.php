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
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PriceBookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_branch_override_wins_and_resolution_reports_source_and_transition(): void
    {
        [$tenant, , $token, $branch] = $this->scenario();
        $service = $this->service($tenant);

        $tenantBook = $this->createBook($tenant, $token, 'Harga Tenant');
        $this->createEntry($tenant, $token, $tenantBook, $service, 8000, '2026-01-01', null)
            ->assertCreated()
            ->assertJsonPath('data.effective_from', '2025-12-31T17:00:00Z');

        $branchBook = $this->createBook($tenant, $token, 'Harga Cabang', $branch);
        $this->createEntry($tenant, $token, $branchBook, $service, 9000, '2026-11-01', '2027-10-31')
            ->assertCreated()
            ->assertJsonPath('data.effective_until', '2027-10-31T17:00:00Z');

        $this->resolve($tenant, $token, $branch, $service, '2026-11-15')
            ->assertOk()
            ->assertJsonPath('data.unit_price_rupiah', 9000)
            ->assertJsonPath('data.source', 'branch')
            ->assertJsonPath('data.price_book.scope', 'branch')
            ->assertJsonPath('data.price_book.branch_id', $branch->public_id)
            ->assertJsonPath('data.effective_from_date', '2026-11-01')
            ->assertJsonPath('data.effective_to_date', '2027-10-31');

        $this->resolve($tenant, $token, $branch, $service, '2027-11-15')
            ->assertOk()
            ->assertJsonPath('data.unit_price_rupiah', 8000)
            ->assertJsonPath('data.source', 'tenant')
            ->assertJsonPath('data.price_book.scope', 'tenant');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id.'/publication', [
                'is_active' => true,
                'reason' => 'Harga tenant aktif',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'is_active' => true,
        ]);

        $log = AuditLog::query()->where('action', AuditAction::ServicePublicationChanged->value)->sole();
        $this->assertSame('service', $log->subject_type);
        $this->assertSame($service->public_id, $log->subject_public_id);
        $this->assertSame('Harga tenant aktif', $log->reason);
        $this->assertTrue($log->redacted_changes['after']['is_active']);
    }

    public function test_publication_requires_an_active_workflow_and_an_effective_tenant_price(): void
    {
        $this->travelTo('2026-10-31 00:00:00');
        [$tenant, , $token] = $this->scenario();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard']);
        $service = $this->service($tenant, workflow: $workflow);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id.'/publication', ['is_active' => true])
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.field_errors.is_active.0',
                'An active tenant-scope regular price is required before publication.',
            );

        $book = $this->createBook($tenant, $token, 'Harga Tenant');
        $this->createEntry($tenant, $token, $book, $service, 8000, '2026-11-01', null)->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id.'/publication', ['is_active' => true])
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.field_errors.is_active.0',
                'An active tenant-scope regular price is required before publication.',
            );

        $this->travelTo('2026-11-02 00:00:00');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id.'/publication', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $staleService = $this->service($tenant, 'satuan', 'Cuci Satuan', $workflow);
        $this->createEntry($tenant, $token, $book, $staleService, 25000, '2026-11-01', null)->assertCreated();

        $this->workflowRequest($tenant, $token, 'standard', [
            ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'drying', 'is_required' => true, 'requires_qc' => false],
            ['code' => 'qc', 'is_required' => true, 'requires_qc' => true],
        ])->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$staleService->public_id.'/publication', ['is_active' => true])
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.field_errors.is_active.0',
                'The service must use an active workflow revision before publication.',
            );
    }

    public function test_overlapping_periods_are_rejected_and_entries_can_be_closed_then_extended(): void
    {
        [$tenant, , $token] = $this->scenario();
        $service = $this->service($tenant);
        $book = $this->createBook($tenant, $token, 'Harga Tenant');

        $first = $this->createEntry($tenant, $token, $book, $service, 8000, '2026-01-01', '2026-12-31')
            ->assertCreated();
        $firstId = $first->json('data.id');

        $this->createEntry($tenant, $token, $book, $service, 9000, '2026-06-01', '2026-12-31')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['effective_from']]]);

        $this->createEntry($tenant, $token, $book, $service, 8500, '2027-01-01', '2027-12-31')
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/price-entries/'.$firstId, ['effective_to' => '2026-06-30'])
            ->assertOk()
            ->assertJsonPath('data.effective_until', '2026-06-30T17:00:00Z')
            ->assertJsonPath('data.version', 2);

        $this->createEntry($tenant, $token, $book, $service, 9500, '2026-07-01', '2026-12-31')
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/price-entries/'.$firstId, ['effective_to' => '2026-12-31'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['effective_to']]]);
    }

    public function test_database_exclusion_constraint_rejects_an_overlap_that_bypasses_the_action(): void
    {
        [$tenant] = $this->scenario();
        $service = $this->service($tenant);
        $book = PriceBook::factory()->for($tenant)->create();
        PriceEntry::factory()->forPriceBook($book, $service)->create([
            'effective_from' => CarbonImmutable::parse('2026-01-01 00:00:00', 'Asia/Jakarta')->utc(),
            'effective_until' => CarbonImmutable::parse('2026-12-31 00:00:00', 'Asia/Jakarta')->addDay()->utc(),
        ]);

        try {
            PriceEntry::factory()->forPriceBook($book, $service)->create([
                'effective_from' => CarbonImmutable::parse('2026-06-01 00:00:00', 'Asia/Jakarta')->utc(),
                'effective_until' => CarbonImmutable::parse('2026-12-31 00:00:00', 'Asia/Jakarta')->addDay()->utc(),
            ]);
            $this->fail('The database must reject overlapping price periods even without the action pre-check.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->errorInfo[0]);
        }
    }

    public function test_new_active_book_for_the_same_scope_replaces_the_previous_one(): void
    {
        [$tenant, , $token, $branch] = $this->scenario();
        $first = $this->createBook($tenant, $token, 'Harga Lama');
        $second = $this->createBook($tenant, $token, 'Harga Baru');

        $this->assertFalse($first->refresh()->is_active);
        $this->assertSame(2, $first->version);
        $this->assertTrue($second->is_active);

        $this->createBook($tenant, $token, 'Harga Cabang', $branch);

        $this->assertTrue($second->refresh()->is_active);
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/price-books?active=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_database_rejects_two_active_books_for_the_same_scope_even_if_the_action_is_bypassed(): void
    {
        [$tenant] = $this->scenario();
        PriceBook::factory()->for($tenant)->create(['is_active' => true]);

        try {
            PriceBook::factory()->for($tenant)->create(['is_active' => true]);
            $this->fail('The database must reject a second active tenant-scope price book.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }
    }

    public function test_price_endpoints_are_tenant_scoped_and_require_manage_permission_for_mutation(): void
    {
        [$tenant, $user, $token, $branch] = $this->scenario();
        $service = $this->service($tenant);
        $otherTenant = Tenant::factory()->create();
        $otherBook = PriceBook::factory()->for($otherTenant)->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/price-books/'.$otherBook->public_id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/price-books/'.$otherBook->public_id.'/entries', [
                'service_id' => $service->public_id,
                'tier' => 'regular',
                'unit_price_rupiah' => 8000,
                'effective_from' => '2026-01-01',
            ])
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/prices/resolve?branch_id='.$branch->public_id.'&service_id='.$service->public_id)
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['service_id']]]);

        $book = $this->createBook($tenant, $token, 'Harga Tenant');
        $role = Role::query()->where('code', 'catalog-admin')->sole();
        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::CatalogView->value)->sole()->id],
            $user,
            'Downgrade to catalog viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/price-books')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/price-books', ['name' => 'Denied'])
            ->assertForbidden();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/price-books/'.$book->public_id.'/entries', [
                'service_id' => $service->public_id,
                'tier' => 'regular',
                'unit_price_rupiah' => 8000,
                'effective_from' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->patchJson('/api/v1/services/'.$service->public_id.'/publication', ['is_active' => true])
            ->assertForbidden();
    }

    private function service(
        Tenant $tenant,
        string $code = 'kiloan',
        string $name = 'Cuci Kering Kiloan',
        ?WorkflowTemplate $workflow = null,
    ): Service {
        $workflow ??= WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard']);

        return Service::factory()->forWorkflow($workflow)->create([
            'code' => $code,
            'name' => $name,
        ]);
    }

    private function createBook(Tenant $tenant, string $token, string $name, ?Branch $branch = null): PriceBook
    {
        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/price-books', [
                'name' => $name,
                'branch_id' => $branch?->public_id,
            ])
            ->assertCreated();

        return PriceBook::query()->where('public_id', $response->json('data.id'))->sole();
    }

    private function createEntry(
        Tenant $tenant,
        string $token,
        PriceBook $book,
        Service $service,
        int $price,
        string $from,
        ?string $to,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/price-books/'.$book->public_id.'/entries', [
                'service_id' => $service->public_id,
                'tier' => 'regular',
                'unit_price_rupiah' => $price,
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
    }

    private function resolve(
        Tenant $tenant,
        string $token,
        Branch $branch,
        Service $service,
        string $at,
    ): TestResponse {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/prices/resolve?branch_id='.$branch->public_id.'&service_id='.$service->public_id.'&at='.$at);
    }

    private function workflowRequest(Tenant $tenant, string $token, string $code, array $steps): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/workflow-templates', [
                'code' => $code,
                'name' => ucfirst($code).' workflow',
                'steps' => $steps,
            ]);
    }

    /** @return array{Tenant, User, string, Branch} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'catalog-admin', 'name' => 'Catalog admin']);
        $branch = Branch::factory()->for($tenant)->create();

        $permissionIds = collect([PermissionCode::CatalogView, PermissionCode::CatalogManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [], [], $user, 'Bootstrap catalog admin');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Price book test device');

        return [$tenant, $user, $issued['token']->plainTextToken, $branch];
    }
}
