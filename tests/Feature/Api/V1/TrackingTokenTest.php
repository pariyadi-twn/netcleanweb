<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderTrackingToken;
use App\Modules\Order\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TrackingTokenTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_issue_and_public_lookup_expose_only_minimal_tracking_data(): void
    {
        [$tenant, $token, $order, $role, $user] = $this->scenario();

        $issued = $this->issue($tenant, $token, $order)->assertCreated()
            ->assertJsonPath('data.order_id', $order->public_id);
        $raw = $issued->json('data.token');

        $stored = OrderTrackingToken::query()->where('order_id', $order->id)->sole();
        $this->assertNotSame($raw, $stored->token_hash);
        $this->assertSame(hash('sha256', $raw), $stored->token_hash);

        $this->getJson('/api/v1/tracking/'.$raw)
            ->assertOk()
            ->assertJsonPath('data.order_number', 'TRACK-1')
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.branch.name', 'Cabang Tracking')
            ->assertJsonPath('data.branch.phone', '081200000000')
            ->assertJsonPath('data.bag_count', 1)
            ->assertJsonMissingPath('data.customer')
            ->assertJsonMissingPath('data.total_rupiah');

        $stored->refresh();
        $this->assertNotNull($stored->last_used_at);

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to order viewer',
        );

        $this->issue($tenant, $token, $order)->assertForbidden();
    }

    public function test_regeneration_and_revocation_invalidate_old_tokens(): void
    {
        [$tenant, $token, $order] = $this->scenario();
        $first = $this->issue($tenant, $token, $order)->assertCreated()->json('data.token');
        $second = $this->issue($tenant, $token, $order)->assertCreated()->json('data.token');

        $this->getJson('/api/v1/tracking/'.$first)->assertNotFound();
        $this->getJson('/api/v1/tracking/'.$second)->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->deleteJson('/api/v1/orders/'.$order->public_id.'/tracking-token')
            ->assertOk()
            ->assertJsonPath('data.revoked', 1);

        $this->getJson('/api/v1/tracking/'.$second)->assertNotFound();
    }

    public function test_expired_token_and_invalid_token_are_not_found(): void
    {
        [$tenant, $token, $order] = $this->scenario();
        $raw = $this->issue($tenant, $token, $order)->assertCreated()->json('data.token');

        $this->getJson('/api/v1/tracking/'.str_repeat('x', 43))->assertNotFound();

        $this->travelTo(CarbonImmutable::now()->addDays(31));
        $this->getJson('/api/v1/tracking/'.$raw)->assertNotFound();
    }

    public function test_public_tracking_is_rate_limited_per_token(): void
    {
        [$tenant, $token, $order] = $this->scenario();
        $raw = $this->issue($tenant, $token, $order)->assertCreated()->json('data.token');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->getJson('/api/v1/tracking/'.$raw)->assertOk();
        }

        $this->getJson('/api/v1/tracking/'.$raw)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    private function issue(Tenant $tenant, string $token, Order $order): TestResponse
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/tracking-token');
    }

    /** @return array{Tenant, string, Order, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create([
            'code' => 'TRK',
            'name' => 'Cabang Tracking',
            'phone' => '081200000000',
        ]);

        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap cashier');

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 24000,
            'pricing_snapshot' => [],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();

        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'TRACK-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'PII Customer', 'normalized_phone' => '081299999999'],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        $invoice = new Invoice([
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'invoice_number' => 'INV-TRACK-1',
            'document_status' => 'issued',
            'currency' => 'IDR',
            'original_total_rupiah' => 24000,
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);
        $invoice->tenant_id = $tenant->id;
        $invoice->save();

        $bag = new Bag([
            'order_id' => $order->id,
            'label_code' => 'BAG-TRACK-1',
            'custody_status' => 'at_location',
        ]);
        $bag->tenant_id = $tenant->id;
        $bag->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Tracking device')['token']->plainTextToken;

        return [$tenant, $token, $order, $role, $user];
    }
}
