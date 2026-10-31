<?php

namespace Database\Factories;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): int => TenantMembership::query()->findOrFail($attributes['uploaded_by_membership_id'])->tenant_id,
            'uploaded_by_membership_id' => TenantMembership::factory(),
            'object_key' => 'attachments/'.Str::uuid(),
            'thumbnail_key' => null,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'expected_sha256' => null,
            'sha256' => hash('sha256', Str::uuid()->toString()),
            'width' => 100,
            'height' => 100,
            'original_name' => 'photo.jpg',
            'state' => AttachmentState::Ready,
        ];
    }
}
