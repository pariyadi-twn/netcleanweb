<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerHistoryConsentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_order_history_is_paginated_and_ordered(): void
    {
        [$tenant, , $token, $branch, $membership] = $this->scenario();
        $customer = Customer::factory()->for($tenant)->create();
        $other = Customer::factory()->for($tenant)->create();

        $this->historyOrder($tenant, $branch, $membership, $customer, 'ORD-1', 10000, '2026-10-01 10:00:00');
        $this->historyOrder($tenant, $branch, $membership, $customer, 'ORD-2', 20000, '2026-10-02 10:00:00');
        $this->historyOrder($tenant, $branch, $membership, $customer, 'ORD-3', 30000, '2026-10-03 10:00:00');
        $this->historyOrder($tenant, $branch, $membership, $other, 'ORD-OTHER', 40000, '2026-10-04 10:00:00');

        $first = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/orders?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_number', 'ORD-3')
            ->assertJsonPath('data.1.order_number', 'ORD-2')
            ->assertJsonPath('data.0.total_rupiah', 30000)
            ->assertJsonPath('meta.per_page', 2);

        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/orders?per_page=2&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'ORD-1');
    }

    public function test_consent_is_separate_per_purpose_and_channel_and_marketing_withdrawal_preserves_service_access(): void
    {
        [$tenant, , $token] = $this->scenario();
        $customer = Customer::factory()->for($tenant)->create();

        $this->postConsent($tenant, $token, $customer, [
            'purpose' => 'service_contact',
            'channel' => 'whatsapp',
            'decision' => 'granted',
            'source' => 'intake form',
        ])->assertCreated()
            ->assertJsonPath('data.decision', 'granted')
            ->assertJsonPath('data.purpose', 'service_contact');

        $this->postConsent($tenant, $token, $customer, [
            'purpose' => 'marketing',
            'channel' => 'email',
            'decision' => 'granted',
            'source' => 'intake form',
        ])->assertCreated();

        $this->postConsent($tenant, $token, $customer, [
            'purpose' => 'marketing',
            'channel' => 'email',
            'decision' => 'withdrawn',
            'source' => 'customer request',
        ])->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/consents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.purpose', 'marketing')
            ->assertJsonPath('data.0.decision', 'withdrawn')
            ->assertJsonPath('data.1.purpose', 'service_contact')
            ->assertJsonPath('data.1.decision', 'granted');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(3, CustomerConsent::query()->where('customer_id', $customer->id)->count());
    }

    public function test_history_and_consent_enforce_permissions_and_tenant_isolation(): void
    {
        [$tenant, $user, $token, , , $role] = $this->scenario();
        $customer = Customer::factory()->for($tenant)->create();
        $otherCustomer = Customer::factory()->create();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$otherCustomer->public_id.'/consents')
            ->assertNotFound();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::CustomersView->value)->sole()->id],
            $user,
            'Downgrade to customer viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/consents')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/customers/'.$customer->public_id.'/orders')
            ->assertForbidden();

        $this->postConsent($tenant, $token, $customer, [
            'purpose' => 'marketing',
            'channel' => 'push',
            'decision' => 'withdrawn',
            'source' => 'customer request',
        ])->assertForbidden();
    }

    private function postConsent(Tenant $tenant, string $token, Customer $customer, array $payload)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/customers/'.$customer->public_id.'/consents', $payload);
    }

    private function historyOrder(
        Tenant $tenant,
        Branch $branch,
        TenantMembership $membership,
        Customer $customer,
        string $number,
        int $total,
        string $createdAt,
    ): Order {
        $created = CarbonImmutable::parse($createdAt);

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => hash('sha256', $number),
            'expires_at' => $created->addMinutes(15),
            'due_at' => $created->addDay(),
            'total_rupiah' => $total,
            'pricing_snapshot' => [],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

        $order = new Order([
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => $number,
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => $created->addDay(),
            'due_at_current' => $created->addDay(),
            'customer_snapshot' => ['name' => $customer->name],
            'accepted_at' => $created,
        ]);
        $order->tenant_id = $tenant->id;
        $order->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

        $invoice = new Invoice([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'sales_branch_id' => $branch->id,
            'invoice_number' => 'INV-'.$number,
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => $total,
            'issued_at' => $created,
            'due_at' => $created->addDay(),
        ]);
        $invoice->tenant_id = $tenant->id;
        $invoice->save();

        return $order;
    }

    /** @return array{Tenant, User, string, Branch, TenantMembership, Role} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'customer-admin', 'name' => 'Customer admin']);
        $branch = Branch::factory()->for($tenant)->create();

        $permissionIds = collect([PermissionCode::CustomersView, PermissionCode::CustomersManage, PermissionCode::OrdersView])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(
                ['code' => $code->value],
                ['description' => $code->name],
            )->id);

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => [
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ])->all());

        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap customer admin');

        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Customer history test device');

        return [$tenant, $user, $issued['token']->plainTextToken, $branch, $membership, $role];
    }
}
