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
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderConditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notes_and_ready_attachments_are_saved_atomically(): void
    {
        [$tenant, $token, $membership, $order, $role, $user] = $this->scenario();
        $attachment = Attachment::factory()->create([
            'uploaded_by_membership_id' => $membership->id,
            'state' => AttachmentState::Ready,
        ]);

        $response = $this->postCondition($tenant, $token, $order, [
            'notes' => 'Noda di bagian kerah, foto terlampir.',
            'attachment_ids' => [$attachment->public_id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.notes', 'Noda di bagian kerah, foto terlampir.')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.id', $attachment->public_id);

        $this->assertDatabaseHas('order_attachments', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'attachment_id' => $attachment->id,
            'purpose' => 'initial_condition',
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/condition')
            ->assertOk()
            ->assertJsonPath('data.notes', 'Noda di bagian kerah, foto terlampir.')
            ->assertJsonCount(1, 'data.attachments');

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->where('code', PermissionCode::OrdersView->value)->sole()->id],
            $user,
            'Downgrade to order viewer',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/orders/'.$order->public_id.'/condition')
            ->assertOk();

        $this->postCondition($tenant, $token, $order, ['notes' => 'Denied', 'attachment_ids' => []])
            ->assertForbidden();
    }

    public function test_pending_attachment_rolls_back_without_saving_evidence(): void
    {
        [$tenant, $token, $membership, $order] = $this->scenario();
        $pending = Attachment::factory()->create([
            'uploaded_by_membership_id' => $membership->id,
            'state' => AttachmentState::Pending,
        ]);

        $this->postCondition($tenant, $token, $order, [
            'notes' => 'Tidak boleh tersimpan',
            'attachment_ids' => [$pending->public_id],
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['attachment_ids']]]);

        $this->assertDatabaseCount('order_condition_evidence', 0);
        $this->assertDatabaseMissing('order_attachments', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'attachment_id' => $pending->id,
        ]);
    }

    public function test_foreign_or_missing_attachments_and_empty_evidence_are_rejected(): void
    {
        [$tenant, $token, , $order] = $this->scenario();
        $otherTenant = Tenant::factory()->create();
        $otherMembership = TenantMembership::factory()->for($otherTenant)->create(['status' => 'active']);
        $foreign = Attachment::factory()->create([
            'uploaded_by_membership_id' => $otherMembership->id,
            'state' => AttachmentState::Ready,
        ]);

        $this->postCondition($tenant, $token, $order, [
            'notes' => 'Foreign',
            'attachment_ids' => [$foreign->public_id],
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['attachment_ids']]]);

        $this->postCondition($tenant, $token, $order, [
            'notes' => null,
            'attachment_ids' => [],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('order_condition_evidence', 0);
    }

    private function postCondition(Tenant $tenant, string $token, Order $order, array $payload)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/condition', $payload);
    }

    /** @return array{Tenant, string, TenantMembership, Order, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'condition-admin', 'name' => 'Condition admin']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap condition admin');

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('e', 64),
            'expires_at' => now()->addMinutes(15),
            'due_at' => now()->addDay(),
            'total_rupiah' => 0,
            'pricing_snapshot' => [],
        ]);
        $quote->tenant_id = $tenant->id;
        $quote->save();
        $order = new Order([
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'COND-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'Walk-in'],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Condition device')['token']->plainTextToken;

        return [$tenant, $token, $membership, $order, $role, $user];
    }
}
