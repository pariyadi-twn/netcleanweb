<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
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
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Reporting\Jobs\BuildReportExport;
use App\Modules\Reporting\Models\ImportBatch;
use App\Modules\Reporting\Models\ReportExport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_import_dry_run_mapping_dedup_errors_and_raw_retention(): void
    {
        Storage::fake('local');
        [$tenant, $token, $branch, $membership] = $this->scenario();

        $existing = new Customer(['name' => 'Ayu', 'normalized_phone' => '081200000001']);
        $existing->tenant_id = $tenant->id;
        $existing->save();

        $csv = implode("\n", [
            'name,phone,email,notes,address_label,address',
            'Ayu,081200000001,,,,',
            'Siti,0812-0000-0002,siti@example.com,Catatan,Rumah,Jl. Mawar',
            'Siti Duplikat,081200000002,,,,',
            ',081200000003,,,,',
        ]);

        $dryRun = $this->upload($tenant, $token, 'customers', $csv, true)
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.valid_rows', 3)
            ->assertJsonPath('data.duplicate_rows', 2)
            ->assertJsonPath('data.invalid_rows', 1)
            ->assertJsonPath('data.imported_rows', 0);

        $this->assertDatabaseMissing('customers', ['normalized_phone' => '081200000002']);

        $actual = $this->upload($tenant, $token, 'customers', $csv, false)
            ->assertCreated()
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('data.duplicate_rows', 2)
            ->assertJsonPath('data.invalid_rows', 1);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Siti',
            'normalized_phone' => '081200000002',
        ]);
        $this->assertSame(2, DB::table('customers')->where('tenant_id', $tenant->id)->count());

        $batchId = $actual->json('data.import_id');
        $batch = ImportBatch::query()->where('public_id', $batchId)->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($batch->raw_object_key));
        $this->assertSame(hash('sha256', $csv), $batch->checksum);

        $rawKey = $batch->raw_object_key;
        $batch->forceFill(['raw_expires_at' => now()->subDay()])->save();
        $this->artisan('imports:purge')->assertSuccessful();
        $this->assertFalse(Storage::disk('local')->exists($rawKey));
        $this->assertNull($batch->refresh()->raw_object_key);
        $this->assertNotNull($batch->raw_purged_at);

        $rows = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/imports/'.$batchId.'/rows')
            ->assertOk();
        $this->assertSame('duplicate', $rows->json('data.0.status'));
        $this->assertSame('imported', $rows->json('data.1.status'));
        $this->assertSame('duplicate', $rows->json('data.2.status'));
        $this->assertSame('invalid', $rows->json('data.3.status'));

        $templates = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->get('/api/v1/imports/templates/customers')
            ->assertOk();
        $this->assertStringContainsString('name,phone,email', $templates->getContent());
    }

    public function test_import_mapping_maps_custom_headers_before_validation(): void
    {
        Storage::fake('local');
        [$tenant, $token] = $this->scenario();

        $csv = "Nama,Telepon\nRina,081200000009\n";
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->post('/api/v1/imports/customers', [
                'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
                'mapping' => json_encode(['Nama' => 'name', 'Telepon' => 'phone']),
            ])
            ->assertCreated()
            ->assertJsonPath('data.imported_rows', 1);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Rina',
            'normalized_phone' => '081200000009',
        ]);
    }

    public function test_catalog_import_creates_service_and_reports_invalid_duplicate(): void
    {
        Storage::fake('local');
        [$tenant, $token] = $this->scenario();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard', 'is_active' => true]);

        $csv = implode("\n", [
            'code,name,unit,minimum_quantity,quantity_step,workflow_template_id,sla_mode,regular_turnaround,express_turnaround',
            'kiloan-baru,Cuci Kiloan Baru,kg,3,0.1,'.$workflow->public_id.',working_days,2,1',
            'kiloan-baru,Duplikat,kg,3,0.1,'.$workflow->public_id.',working_days,2,1',
            'rusak,Rusak,kg,3,0.1,00000000-0000-0000-0000-000000000000,working_days,2,1',
        ]);

        $this->upload($tenant, $token, 'catalog', $csv, false)
            ->assertCreated()
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('data.duplicate_rows', 1)
            ->assertJsonPath('data.invalid_rows', 1);

        $this->assertDatabaseHas('services', ['tenant_id' => $tenant->id, 'code' => 'kiloan-baru']);
        $this->assertSame(1, Service::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_export_is_async_ready_downloadable_reauthorized_and_expires(): void
    {
        Storage::fake('local');
        [$tenant, $token, $branch, , $role, $user] = $this->scenario(export: true);
        $command = (string) Str::uuid();

        $request = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/reports/exports', [
                'client_command_id' => $command,
                'report_type' => 'outlet_sales',
                'filters' => [
                    'branch_id' => $branch->public_id,
                    'from' => '2026-10-31',
                    'to' => '2026-11-01',
                ],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        $exportId = $request->json('data.export_id');

        $status = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.export_id', $exportId);
        $this->assertContains($status->json('data.status'), ['queued', 'ready']);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->get('/api/v1/reports/exports/'.$exportId.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/reports/exports', [
                'client_command_id' => $command,
                'report_type' => 'outlet_sales',
                'filters' => [
                    'branch_id' => $branch->public_id,
                    'from' => '2026-10-31',
                    'to' => '2026-11-01',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $export = ReportExport::query()->where('public_id', $exportId)->firstOrFail();
        $this->assertSame(ReportExportStatus::Ready, $export->status);
        $this->assertNotNull($export->attachment_id);
        $this->assertSame('report_export', $export->attachment->purpose);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/attachments/'.$export->attachment->public_id.'/url')
            ->assertNotFound();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::ReportsView->value)->sole()->id],
            $user,
            'Remove export permission',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->get('/api/v1/reports/exports/'.$exportId.'/download')
            ->assertForbidden();

        $export->forceFill(['expires_at' => now()->subMinute()])->save();
        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::ReportsExport->value)->sole()->id],
            $user,
            'Restore export permission',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->get('/api/v1/reports/exports/'.$exportId.'/download')
            ->assertStatus(410);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');
    }

    public function test_export_generation_reauthorizes_the_requester(): void
    {
        Storage::fake('local');
        [$tenant, , $branch, $membership, $role, $user] = $this->scenario(export: true);

        $export = new ReportExport([
            'requested_by_membership_id' => $membership->id,
            'requested_by_user_id' => $user->id,
            'report_type' => 'outlet_sales',
            'filters' => ['branch_id' => $branch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-01'],
            'status' => ReportExportStatus::Queued,
            'expires_at' => now()->addDay(),
        ]);
        $export->tenant_id = $tenant->id;
        $export->save();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::ReportsView->value)->sole()->id],
            $user,
            'Revoke export before generation',
        );

        BuildReportExport::dispatchSync($export->id, $tenant->id, $user->id);

        $export->refresh();
        $this->assertSame(ReportExportStatus::Failed, $export->status);
        $this->assertSame('EXPORT_AUTHORIZATION_REVOKED', $export->error_code);
    }

    public function test_export_quota_and_tenant_scope_are_enforced(): void
    {
        Storage::fake('local');
        [$tenant, $token, $branch, $membership] = $this->scenario(export: true);
        $membershipId = $membership->id;
        $other = Tenant::factory()->create();
        $otherBranch = Branch::factory()->for($other)->create();

        for ($i = 0; $i < 3; $i++) {
            $export = new ReportExport([
                'requested_by_membership_id' => TenantMembership::query()->where('tenant_id', $tenant->id)->value('id'),
                'requested_by_user_id' => User::query()->firstOrFail()->id,
                'report_type' => 'outlet_sales',
                'filters' => ['branch_id' => $branch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-01'],
                'status' => ReportExportStatus::Queued,
                'expires_at' => now()->addDay(),
            ]);
            $export->tenant_id = $tenant->id;
            $export->save();
        }

        $command = (string) Str::uuid();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/reports/exports', [
                'client_command_id' => $command,
                'report_type' => 'outlet_sales',
                'filters' => ['branch_id' => $branch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-01'],
            ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'EXPORT_QUOTA_EXCEEDED');

        $foreignMembership = TenantMembership::factory()->for($other)->create(['status' => 'active']);
        $foreignExport = new ReportExport([
            'requested_by_membership_id' => $foreignMembership->id,
            'requested_by_user_id' => User::query()->firstOrFail()->id,
            'report_type' => 'outlet_sales',
            'filters' => ['branch_id' => $otherBranch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-01'],
            'status' => ReportExportStatus::Queued,
            'expires_at' => now()->addDay(),
        ]);
        $foreignExport->tenant_id = $other->id;
        $foreignExport->save();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/exports/'.$foreignExport->public_id)
            ->assertNotFound();
    }

    private function upload(Tenant $tenant, string $token, string $type, string $csv, bool $dryRun)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->post('/api/v1/imports/'.$type, [
                'file' => UploadedFile::fake()->createWithContent($type.'.csv', $csv),
                'dry_run' => $dryRun,
            ]);
    }

    /** @return array{Tenant, string, Branch, TenantMembership, Role, User} */
    private function scenario(bool $export = false): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);
        $branch = Branch::factory()->for($tenant)->create(['timezone' => 'Asia/Jakarta']);
        $codes = [PermissionCode::ImportsManage, PermissionCode::ReportsView];
        if ($export) {
            $codes[] = PermissionCode::ReportsExport;
        }
        $permissions = collect($codes)
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap import/export owner');

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Import/export device')['token']->plainTextToken;

        return [$tenant, $token, $branch, $membership, $role, $user];
    }
}
