<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowStep;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\ProvisioningScope;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Operations\Models\BranchCalendar;
use App\Modules\Operations\Models\CommandReceipt;
use App\Modules\Operations\Models\OutboxEvent;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Modules\Saas\Actions\ConsumeQuota;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\TenantSubscription;
use App\Modules\Saas\Models\TenantUsageCounter;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRlsTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_runtime_role_without_context_is_denied(): void
    {
        $tenant = Tenant::factory()->create();
        Branch::factory()->for($tenant)->create();
        $runtime = DB::connection('pgsql_runtime');

        $this->assertSame(0, $runtime->table('tenants')->count());
        $this->assertSame(0, $runtime->table('tenant_memberships')->count());
        $this->assertSame(0, $runtime->table('branches')->count());

        try {
            $runtime->table('branches')->insert([
                'tenant_id' => $tenant->id,
                'code' => 'RLS-1',
                'name' => 'Denied branch',
                'timezone' => 'Asia/Jakarta',
                'address' => 'Denied address',
            ]);
            $this->fail('Runtime role must not insert a branch without tenant context.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        }
    }

    public function test_identity_scope_only_exposes_the_users_own_tenants_and_assignments(): void
    {
        $user = User::factory()->create();
        [$tenantA, , $branchA, $locationA] = $this->createTenantFor($user, 'owner-a');
        [$tenantB, , $branchB, $locationB] = $this->createTenantFor($user, 'owner-b');

        $otherUser = User::factory()->create();
        [$otherTenant, , $otherBranch] = $this->createTenantFor($otherUser, 'other-owner');

        $context = app(TenantScope::class)->identity($user, function () use ($tenantA, $tenantB, $branchA, $branchB, $locationA, $locationB): array {
            return [
                'tenant_ids' => Tenant::query()->orderBy('id')->pluck('public_id')->all(),
                'membership_count' => TenantMembership::query()->count(),
                'branch_ids' => Branch::query()->orderBy('id')->pluck('public_id')->all(),
                'location_ids' => Location::query()->orderBy('id')->pluck('public_id')->all(),
                'expected' => [$tenantA->public_id, $tenantB->public_id, $branchA->public_id, $branchB->public_id, $locationA->public_id, $locationB->public_id],
            ];
        }, 'pgsql_runtime');

        $this->assertSame([$tenantA->public_id, $tenantB->public_id], $context['tenant_ids']);
        $this->assertSame(2, $context['membership_count']);
        $this->assertSame([$branchA->public_id, $branchB->public_id], $context['branch_ids']);
        $this->assertSame([$locationA->public_id, $locationB->public_id], $context['location_ids']);
        $this->assertNotContains($otherTenant->public_id, $context['tenant_ids']);
        $this->assertNotContains($otherBranch->public_id, $context['branch_ids']);
    }

    public function test_tenant_scope_filters_rows_and_rejects_cross_tenant_writes(): void
    {
        $user = User::factory()->create();
        [$tenantA, , $branchA] = $this->createTenantFor($user, 'owner-a');
        [$tenantB, , $branchB] = $this->createTenantFor($user, 'owner-b');

        app(TenantScope::class)->forTenant($tenantA, $user, function () use ($tenantA, $tenantB, $branchA): void {
            $this->assertSame(1, Tenant::query()->count());
            $this->assertSame(1, TenantMembership::query()->count());
            $this->assertSame(1, Branch::query()->count());
            $this->assertSame($tenantA->public_id, Tenant::query()->value('public_id'));
            $this->assertSame($branchA->public_id, Branch::query()->value('public_id'));
            $this->assertSame(0, DB::connection('pgsql_runtime')->table('branches')->where('tenant_id', $tenantB->id)->count());

            try {
                DB::connection('pgsql_runtime')->table('branches')->insert([
                    'tenant_id' => $tenantB->id,
                    'code' => 'RLS-2',
                    'name' => 'Cross tenant branch',
                    'timezone' => 'Asia/Jakarta',
                    'address' => 'Denied address',
                ]);
                $this->fail('Cross-tenant insert must be rejected by RLS.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->errorInfo[0]);
            }
        }, 'pgsql_runtime');

        $this->assertNotSame($branchA->public_id, $branchB->public_id);
    }

    public function test_tenant_scope_settings_are_cleared_after_the_transaction(): void
    {
        $user = User::factory()->create();
        [$tenant] = $this->createTenantFor($user, 'owner');

        $count = app(TenantScope::class)->forTenant($tenant, $user, fn (): int => Branch::query()->count(), 'pgsql_runtime');

        $this->assertSame(1, $count);
        $this->assertSame(0, (int) DB::connection('pgsql_runtime')->table('branches')->count());
        $this->assertEmpty(DB::connection('pgsql_runtime')->selectOne("SELECT current_setting('app.tenant_id', true) AS tenant_id")->tenant_id);
    }

    public function test_runtime_catalog_scope_filters_workflows_steps_and_services(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $workflowA = WorkflowTemplate::factory()->for($tenantA)->create(['code' => 'standard-a']);
        $workflowB = WorkflowTemplate::factory()->for($tenantB)->create(['code' => 'standard-b']);
        WorkflowStep::factory()->forWorkflow($workflowA)->create(['code' => 'washing']);
        WorkflowStep::factory()->forWorkflow($workflowB)->create(['code' => 'washing']);
        $serviceA = Service::factory()->forWorkflow($workflowA)->create(['code' => 'kiloan-a']);
        $serviceB = Service::factory()->forWorkflow($workflowB)->create(['code' => 'kiloan-b']);

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => [
            'workflows' => WorkflowTemplate::query()->pluck('public_id')->all(),
            'steps' => WorkflowStep::query()->pluck('public_id')->all(),
            'services' => Service::query()->pluck('public_id')->all(),
        ], 'pgsql_runtime');

        $this->assertSame([$workflowA->public_id], $visible['workflows']);
        $this->assertCount(1, $visible['steps']);
        $this->assertSame([$serviceA->public_id], $visible['services']);
        $this->assertNotContains($workflowB->public_id, $visible['workflows']);
        $this->assertNotContains($serviceB->public_id, $visible['services']);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('services')->count());
    }

    public function test_runtime_price_book_and_entry_scope_are_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $workflowA = WorkflowTemplate::factory()->for($tenantA)->create();
        $workflowB = WorkflowTemplate::factory()->for($tenantB)->create();
        $serviceA = Service::factory()->forWorkflow($workflowA)->create();
        $serviceB = Service::factory()->forWorkflow($workflowB)->create();
        $bookA = PriceBook::factory()->for($tenantA)->create();
        $bookB = PriceBook::factory()->for($tenantB)->create();
        $entryA = PriceEntry::factory()->forPriceBook($bookA, $serviceA)->create();
        $entryB = PriceEntry::factory()->forPriceBook($bookB, $serviceB)->create();

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => [
            'books' => PriceBook::query()->pluck('public_id')->all(),
            'entries' => PriceEntry::query()->pluck('public_id')->all(),
        ], 'pgsql_runtime');

        $this->assertSame([$bookA->public_id], $visible['books']);
        $this->assertSame([$entryA->public_id], $visible['entries']);
        $this->assertNotContains($bookB->public_id, $visible['books']);
        $this->assertNotContains($entryB->public_id, $visible['entries']);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('price_books')->count());
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('price_entries')->count());
    }

    public function test_runtime_branch_calendar_scope_is_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $branchA = Branch::factory()->for($tenantA)->create();
        $branchB = Branch::factory()->for($tenantB)->create();
        $calendarA = BranchCalendar::factory()->forBranch($branchA)->create();
        $calendarB = BranchCalendar::factory()->forBranch($branchB)->create();

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => BranchCalendar::query()->pluck('public_id')->all(), 'pgsql_runtime');

        $this->assertSame([$calendarA->public_id], $visible);
        $this->assertNotContains($calendarB->public_id, $visible);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('branch_calendars')->count());
    }

    public function test_runtime_quote_and_order_scope_are_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $branchA = Branch::factory()->for($tenantA)->create();
        $branchB = Branch::factory()->for($tenantB)->create();
        $membershipA = TenantMembership::factory()->for($tenantA)->for($user)->create(['status' => 'active']);
        $membershipB = TenantMembership::factory()->for($tenantB)->for($user)->create(['status' => 'active']);
        $quoteA = $this->quote($tenantA, $branchA);
        $quoteB = $this->quote($tenantB, $branchB);
        $orderA = $this->order($tenantA, $branchA, $membershipA, $quoteA, 'ORDER-A');
        $orderB = $this->order($tenantB, $branchB, $membershipB, $quoteB, 'ORDER-B');

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => [
            'quotes' => Quote::query()->pluck('public_id')->all(),
            'orders' => Order::query()->pluck('public_id')->all(),
        ], 'pgsql_runtime');

        $this->assertSame([$quoteA->public_id], $visible['quotes']);
        $this->assertSame([$orderA->public_id], $visible['orders']);
        $this->assertNotContains($quoteB->public_id, $visible['quotes']);
        $this->assertNotContains($orderB->public_id, $visible['orders']);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('quotes')->count());
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('orders')->count());
    }

    public function test_runtime_customer_consent_scope_is_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $customerA = Customer::factory()->for($tenantA)->create();
        $customerB = Customer::factory()->for($tenantB)->create();
        $consentA = CustomerConsent::factory()->forCustomer($customerA)->create();
        $consentB = CustomerConsent::factory()->forCustomer($customerB)->create();

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => CustomerConsent::query()->pluck('public_id')->all(), 'pgsql_runtime');

        $this->assertSame([$consentA->public_id], $visible);
        $this->assertNotContains($consentB->public_id, $visible);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('customer_consents')->count());
    }

    public function test_runtime_attachment_scope_is_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $membershipA = TenantMembership::factory()->for($tenantA)->for($user)->create(['status' => 'active']);
        $membershipB = TenantMembership::factory()->for($tenantB)->for($user)->create(['status' => 'active']);
        $attachmentA = Attachment::factory()->create(['uploaded_by_membership_id' => $membershipA->id]);
        $attachmentB = Attachment::factory()->create(['uploaded_by_membership_id' => $membershipB->id]);

        $visible = app(TenantScope::class)->forTenant($tenantA, $user, fn (): array => Attachment::query()->pluck('public_id')->all(), 'pgsql_runtime');

        $this->assertSame([$attachmentA->public_id], $visible);
        $this->assertNotContains($attachmentB->public_id, $visible);
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('attachments')->count());
    }

    public function test_worker_can_scope_explicitly_to_one_tenant(): void
    {
        $user = User::factory()->create();
        [$tenantA, , $branchA] = $this->createTenantFor($user, 'owner-a');
        [$tenantB, , $branchB] = $this->createTenantFor($user, 'owner-b');

        $visible = app(TenantScope::class)->forTenantId($tenantB->id, $user->id, fn (): string => Branch::query()->value('public_id'), 'pgsql_runtime');

        $this->assertSame($branchB->public_id, $visible);
        $this->assertNotSame($branchA->public_id, $visible);
        $this->assertSame(0, (int) DB::connection('pgsql_runtime')->table('branches')->count());
    }

    public function test_tenant_middleware_isolates_api_requests_and_requires_a_valid_context(): void
    {
        $user = User::factory()->create();
        [$tenantA, , $branchA] = $this->createTenantFor($user, 'owner-a');
        [$tenantB, , $branchB] = $this->createTenantFor($user, 'owner-b');

        $otherUser = User::factory()->create();
        [$otherTenant] = $this->createTenantFor($otherUser, 'other-owner');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'RLS Test Device');

        Route::middleware(['auth:sanctum', 'active', 'tenant'])->get('/api/v1/test/scoped-branches', function () {
            return response()->json([
                'data' => Branch::query()->orderBy('id')->pluck('public_id')->all(),
            ]);
        });

        config(['database.default' => 'pgsql_runtime']);

        try {
            $this->withToken($issued['token']->plainTextToken)
                ->withHeader('X-Tenant-ID', $tenantA->public_id)
                ->getJson('/api/v1/test/scoped-branches')
                ->assertOk()
                ->assertJsonPath('data', [$branchA->public_id]);

            $this->withToken($issued['token']->plainTextToken)
                ->withHeader('X-Tenant-ID', $tenantB->public_id)
                ->getJson('/api/v1/test/scoped-branches')
                ->assertOk()
                ->assertJsonPath('data', [$branchB->public_id]);

            $this->withToken($issued['token']->plainTextToken)
                ->withHeader('X-Tenant-ID', $otherTenant->public_id)
                ->getJson('/api/v1/test/scoped-branches')
                ->assertNotFound()
                ->assertJsonPath('error.code', 'NOT_FOUND');

            $this->withToken($issued['token']->plainTextToken)
                ->withHeader('X-Tenant-ID', '')
                ->getJson('/api/v1/test/scoped-branches')
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        } finally {
            config(['database.default' => 'pgsql']);
        }
    }

    public function test_audit_logs_are_append_only_and_require_tenant_context_for_runtime(): void
    {
        $user = User::factory()->create();
        [$tenant] = $this->createTenantFor($user, 'owner');

        DB::table('audit_logs')->delete();

        $log = app(AuditRecorder::class)->record(
            tenantId: $tenant->id,
            action: AuditAction::MembershipAssignmentsUpdated,
            subjectType: AuditRecorder::SUBJECT_TENANT_MEMBERSHIP,
            subjectPublicId: null,
            changes: ['role_ids' => []],
            actor: $user,
        );

        $runtime = DB::connection('pgsql_runtime');

        $this->assertSame(0, $runtime->table('audit_logs')->count());

        $visible = app(TenantScope::class)->forTenant($tenant, $user, fn (): int => AuditLog::query()->count(), 'pgsql_runtime');
        $this->assertSame(1, $visible);

        foreach ([
            fn () => $runtime->table('audit_logs')->where('id', $log->id)->update(['reason' => 'tampered']),
            fn () => $runtime->table('audit_logs')->where('id', $log->id)->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Runtime role must not mutate append-only audit logs.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->errorInfo[0]);
            }
        }

        app(TenantScope::class)->forTenant($tenant, $user, function () use ($tenant, $user): void {
            app(AuditRecorder::class)->record(
                tenantId: $tenant->id,
                action: AuditAction::RolePermissionsUpdated,
                subjectType: AuditRecorder::SUBJECT_ROLE,
                subjectPublicId: null,
                changes: ['permission_ids' => []],
                actor: $user,
            );
        }, 'pgsql_runtime');

        $this->assertSame(2, app(TenantScope::class)->forTenant($tenant, $user, fn (): int => AuditLog::query()->count(), 'pgsql_runtime'));
    }

    public function test_command_receipts_require_tenant_context_for_runtime(): void
    {
        $user = User::factory()->create();
        [$tenant, $membership] = $this->createTenantFor($user, 'owner');

        $receipt = CommandReceipt::query()->create([
            'tenant_id' => $tenant->id,
            'client_command_id' => (string) Str::uuid(),
            'command_type' => 'probe.command',
            'request_hash' => hash('sha256', 'probe'),
            'actor_membership_id' => $membership->id,
            'status' => 'processing',
        ]);

        $runtime = DB::connection('pgsql_runtime');

        $this->assertSame(0, $runtime->table('command_receipts')->count());
        $this->assertSame(1, app(TenantScope::class)->forTenant($tenant, $user, fn (): int => CommandReceipt::query()->count(), 'pgsql_runtime'));

        try {
            $runtime->table('command_receipts')->insert([
                'tenant_id' => $tenant->id,
                'client_command_id' => (string) Str::uuid(),
                'command_type' => 'probe.command',
                'request_hash' => hash('sha256', 'probe-2'),
                'actor_membership_id' => $membership->id,
                'status' => 'processing',
            ]);
            $this->fail('Runtime role must not insert a command receipt without tenant context.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        }

        $this->assertNotNull($receipt->id);
    }

    public function test_outbox_events_require_tenant_context_for_runtime(): void
    {
        $user = User::factory()->create();
        [$tenant, $membership] = $this->createTenantFor($user, 'owner');

        DB::table('consumer_receipts')->delete();
        DB::table('event_deliveries')->delete();
        DB::table('outbox_events')->delete();

        $event = app(OutboxWriter::class)->record(
            tenantId: $tenant->id,
            eventType: 'membership.assignments_updated',
            aggregatePublicId: $membership->public_id,
            payload: ['membership_id' => $membership->public_id],
        );

        $runtime = DB::connection('pgsql_runtime');

        $this->assertSame(0, $runtime->table('outbox_events')->count());
        $this->assertSame(1, app(TenantScope::class)->forTenant($tenant, $user, fn (): int => OutboxEvent::query()->count(), 'pgsql_runtime'));

        try {
            $runtime->table('outbox_events')->insert([
                'tenant_id' => $tenant->id,
                'event_id' => (string) Str::uuid(),
                'event_type' => 'membership.assignments_updated',
                'aggregate_public_id' => $membership->public_id,
                'payload' => DB::raw("'{}'::jsonb"),
            ]);
            $this->fail('Runtime role must not insert an outbox event without tenant context.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        }

        $this->assertTrue(Str::isUuid($event->event_id));
    }

    public function test_saas_subscription_and_usage_require_tenant_context_for_runtime(): void
    {
        $user = User::factory()->create();
        [$tenant] = $this->createTenantFor($user, 'owner');

        $plan = SaasPlan::factory()->create([
            'features' => ['limits' => ['staff' => 2], 'features' => ['exports' => false]],
        ]);
        app(StartSubscription::class)->handle($tenant, $plan);
        app(ConsumeQuota::class)->handle($tenant->id, 'staff');

        $runtime = DB::connection('pgsql_runtime');

        $this->assertSame(0, $runtime->table('saas_subscriptions')->count());
        $this->assertSame(0, $runtime->table('tenant_usage_counters')->count());
        $this->assertSame(1, app(TenantScope::class)->forTenant($tenant, $user, fn (): int => TenantSubscription::query()->count(), 'pgsql_runtime'));
        $this->assertSame(1, app(TenantScope::class)->forTenant($tenant, $user, fn (): int => TenantUsageCounter::query()->count(), 'pgsql_runtime'));
    }

    public function test_provisioning_scope_is_required_to_insert_a_tenant_for_runtime(): void
    {
        $user = User::factory()->create();

        $tenant = app(ProvisioningScope::class)->run($user, function (): Tenant {
            $scope = app(ProvisioningScope::class);
            $tenantId = (int) DB::selectOne("SELECT nextval(pg_get_serial_sequence('tenants', 'id')) AS id")->id;
            $scope->activateTenant($tenantId);

            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => 'Runtime Provisioned',
                'slug' => 'runtime-'.mb_strtolower(Str::random(8)),
                'timezone' => 'Asia/Jakarta',
                'status' => TenantStatus::Active->value,
            ]);

            return Tenant::query()->findOrFail($tenantId);
        }, 'pgsql_runtime');

        $this->assertTrue(Str::isUuid($tenant->public_id));
        $this->assertSame(0, DB::connection('pgsql_runtime')->table('tenants')->count());

        try {
            DB::connection('pgsql_runtime')->table('tenants')->insert([
                'name' => 'Denied',
                'slug' => 'denied-'.mb_strtolower(Str::random(8)),
                'timezone' => 'Asia/Jakarta',
                'status' => 'active',
            ]);
            $this->fail('Runtime role must not insert a tenant without provisioning context.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        }
    }

    /**
     * @return array{Tenant, TenantMembership, Branch, Location, Role}
     */
    public function test_runtime_notification_deliveries_require_tenant_context_and_isolate_tenants(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        [$tenantA] = $this->createTenantFor($userA, 'owner');
        [$tenantB] = $this->createTenantFor($userB, 'owner');

        $delivery = new NotificationDelivery([
            'event_id' => (string) Str::uuid(),
            'channel' => NotificationChannel::Email,
            'business_key' => 'rls:notification',
            'recipient_ref' => 'rls@example.com',
            'subject' => 'Nota',
            'message' => 'Pesan uji',
            'payload' => [],
            'status' => NotificationStatus::Queued,
        ]);
        $delivery->tenant_id = $tenantA->id;
        $delivery->save();

        $runtime = DB::connection('pgsql_runtime');
        $this->assertSame(0, $runtime->table('notification_deliveries')->count());
        $this->assertSame(0, app(TenantScope::class)->forTenant($tenantB, $userB, fn (): int => NotificationDelivery::query()->count(), 'pgsql_runtime'));
        $this->assertSame(1, app(TenantScope::class)->forTenant($tenantA, $userA, fn (): int => NotificationDelivery::query()->count(), 'pgsql_runtime'));

        try {
            $runtime->table('notification_deliveries')->insert([
                'tenant_id' => $tenantA->id,
                'event_id' => (string) Str::uuid(),
                'channel' => 'email',
                'business_key' => 'rls:denied',
                'recipient_ref' => 'denied@example.com',
                'subject' => 'Denied',
                'message' => 'Denied',
                'payload' => DB::raw("'{}'::jsonb"),
                'status' => 'queued',
            ]);
            $this->fail('Runtime role must not insert a notification delivery without tenant context.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        }
    }

    private function createTenantFor(User $user, string $roleCode): array
    {
        $tenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();
        $role = Role::factory()->for($tenant)->create(['code' => $roleCode, 'name' => ucfirst($roleCode)]);

        $permissionIds = collect([
            PermissionCode::BranchesView,
            PermissionCode::BranchesManage,
            PermissionCode::LocationsView,
            PermissionCode::LocationsManage,
        ])->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
            ['code' => $code->value],
            ['description' => $code->name],
        )->id)->all();

        DB::table('role_permissions')->insert(array_map(
            fn (int $permissionId): array => [
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ],
            $permissionIds,
        ));

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [$location->id]);

        return [$tenant, $membership, $branch, $location, $role];
    }

    private function quote(Tenant $tenant, Branch $branch): Quote
    {
        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 0,
            'pricing_snapshot' => [],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();

        return $quote;
    }

    private function order(Tenant $tenant, Branch $branch, TenantMembership $membership, Quote $quote, string $number): Order
    {
        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => $number,
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => [],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        return $order;
    }
}
