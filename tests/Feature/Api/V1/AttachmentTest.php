<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_image_is_initialized_transferred_finalized_and_signed(): void
    {
        [$tenant, $token, $membership] = $this->scenario();
        Storage::fake('local');
        $file = UploadedFile::fake()->image('photo.jpg', 120, 80);
        $sha = hash_file('sha256', $file->getRealPath());

        $init = $this->init($tenant, $token, $file, $sha)->assertCreated()
            ->assertJsonPath('data.state', 'pending');
        $attachmentId = $init->json('data.id');

        $this->transfer($tenant, $token, $attachmentId, $file)
            ->assertOk()
            ->assertJsonPath('data.state', 'pending');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/attachments/'.$attachmentId.'/finalize')
            ->assertOk()
            ->assertJsonPath('data.state', 'ready')
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.width', 120)
            ->assertJsonPath('data.height', 80)
            ->assertJsonPath('data.has_thumbnail', true)
            ->assertJsonPath('data.sha256', $sha);

        $attachment = Attachment::query()->where('public_id', $attachmentId)->sole();
        $this->assertSame(AttachmentState::Ready, $attachment->state);
        $this->assertNotNull($attachment->thumbnail_key);
        $this->assertTrue(Storage::disk('local')->exists($attachment->object_key));
        $this->assertTrue(Storage::disk('local')->exists($attachment->thumbnail_key));

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/attachments/'.$attachmentId.'/url')
            ->assertOk()
            ->assertJsonPath('data.url', fn ($url) => is_string($url) && str_contains($url, 'expiration'));
    }

    public function test_invalid_image_and_checksum_are_quarantined(): void
    {
        [$tenant, $token] = $this->scenario();
        Storage::fake('local');
        $bad = UploadedFile::fake()->createWithContent('bad.jpg', 'not an image');

        $init = $this->init($tenant, $token, $bad, hash('sha256', 'not an image'))->assertCreated();
        $id = $init->json('data.id');
        $this->transfer($tenant, $token, $id, $bad)->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/attachments/'.$id.'/finalize')
            ->assertUnprocessable();

        $this->assertSame(AttachmentState::Quarantined, Attachment::query()->where('public_id', $id)->sole()->state);

        $valid = UploadedFile::fake()->image('valid.jpg');
        $wrongChecksum = hash('sha256', 'wrong');
        $second = $this->init($tenant, $token, $valid, $wrongChecksum)->assertCreated();
        $secondId = $second->json('data.id');
        $this->transfer($tenant, $token, $secondId, $valid)->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/attachments/'.$secondId.'/finalize')
            ->assertUnprocessable();

        $this->assertSame(AttachmentState::Quarantined, Attachment::query()->where('public_id', $secondId)->sole()->state);
    }

    public function test_only_uploader_can_access_orphan_and_linked_files_follow_order_scope(): void
    {
        [$tenant, $token, $membership, $order] = $this->scenario();
        Storage::fake('local');
        $other = User::factory()->create();
        $otherMembership = TenantMembership::factory()->for($tenant)->for($other)->create(['status' => 'active']);
        $viewerRole = Role::factory()->for($tenant)->create(['code' => 'attachment-viewer', 'name' => 'Attachment viewer']);
        $viewPermission = Permission::query()->firstOrCreate(['code' => PermissionCode::OrdersView->value], ['description' => PermissionCode::OrdersView->name]);
        DB::table('role_permissions')->insert([
            'tenant_id' => $tenant->id,
            'role_id' => $viewerRole->id,
            'permission_id' => $viewPermission->id,
        ]);
        app(UpdateMembershipAssignments::class)->handle($otherMembership, [$viewerRole->id], [$order->sales_branch_id], [], $other, 'Bootstrap attachment viewer');
        $otherToken = app(IssueDeviceToken::class)->handle($other, (string) Str::uuid(), 'Other device')['token']->plainTextToken;

        $attachment = Attachment::factory()->create([
            'uploaded_by_membership_id' => $membership->id,
            'state' => AttachmentState::Ready,
        ]);
        Storage::disk('local')->put($attachment->object_key, 'file');

        $this->withToken($otherToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/attachments/'.$attachment->public_id.'/url')
            ->assertForbidden();

        DB::table('order_attachments')->insert([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'attachment_id' => $attachment->id,
            'purpose' => 'initial_condition',
            'created_at' => now(),
        ]);

        $this->withToken($otherToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/attachments/'.$attachment->public_id.'/url')
            ->assertOk();

        $this->withToken($otherToken)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/attachments', [
                'attachment_id' => $attachment->public_id,
                'purpose' => 'initial_condition',
            ])
            ->assertForbidden();
    }

    public function test_cleanup_removes_only_old_orphans_and_keeps_active_attachments(): void
    {
        [$tenant, $token, $membership, $order] = $this->scenario();
        Storage::fake('local');

        $orphan = Attachment::factory()->create([
            'uploaded_by_membership_id' => $membership->id,
            'state' => AttachmentState::Pending,
            'object_key' => 'attachments/orphan.jpg',
        ]);
        $orphan->forceFill(['created_at' => now()->subDays(2)])->save();
        Storage::disk('local')->put('attachments/orphan.jpg', 'orphan');

        $active = Attachment::factory()->create([
            'uploaded_by_membership_id' => $membership->id,
            'state' => AttachmentState::Ready,
            'object_key' => 'attachments/active.jpg',
        ]);
        DB::table('order_attachments')->insert([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'attachment_id' => $active->id,
            'purpose' => 'initial_condition',
            'created_at' => now(),
        ]);
        Storage::disk('local')->put('attachments/active.jpg', 'active');

        $this->artisan('attachments:cleanup')->assertSuccessful();

        $this->assertSame(AttachmentState::Rejected, $orphan->refresh()->state);
        $this->assertSame(AttachmentState::Ready, $active->refresh()->state);
        $this->assertFalse(Storage::disk('local')->exists('attachments/orphan.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('attachments/active.jpg'));
    }

    private function init(Tenant $tenant, string $token, UploadedFile $file, string $sha)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/attachments/uploads', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256' => $sha,
            ]);
    }

    private function transfer(Tenant $tenant, string $token, string $attachmentId, UploadedFile $file)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->put('/api/v1/attachments/'.$attachmentId.'/content', ['file' => $file], ['Accept' => 'application/json']);
    }

    /** @return array{Tenant, string, TenantMembership, Order} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'uploader', 'name' => 'Uploader']);
        $branch = Branch::factory()->for($tenant)->create();
        $permissions = collect([PermissionCode::OrdersView, PermissionCode::OrdersManage])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap uploader');

        $quote = new Quote([
            'sales_branch_id' => $branch->id,
            'fulfillment_method' => 'pickup',
            'pickup_branch_id' => $branch->id,
            'request_hash' => str_repeat('d', 64),
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
            'order_number' => 'UPLOAD-ORDER-1',
            'operational_status' => 'accepted',
            'fulfillment_method' => 'pickup',
            'due_at_original' => now()->addDay(),
            'due_at_current' => now()->addDay(),
            'customer_snapshot' => ['name' => 'Walk-in'],
            'accepted_at' => now(),
        ]);
        $order->tenant_id = $tenant->id;
        $order->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Upload device')['token']->plainTextToken;

        return [$tenant, $token, $membership, $order];
    }
}
