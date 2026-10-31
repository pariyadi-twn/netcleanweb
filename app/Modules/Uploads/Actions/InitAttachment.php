<?php

namespace App\Modules\Uploads\Actions;

use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InitAttachment
{
    /**
     * @param  array{original_name?: string|null, mime_type?: string|null, size_bytes: int, sha256?: string|null}  $attributes
     */
    public function handle(int $tenantId, int $membershipId, array $attributes): Attachment
    {
        if ($attributes['size_bytes'] > (int) config('uploads.max_bytes')) {
            throw ValidationException::withMessages([
                'size_bytes' => ['The file exceeds the five megabyte limit.'],
            ]);
        }

        $attachment = new Attachment([
            'uploaded_by_membership_id' => $membershipId,
            'object_key' => 'attachments/'.$tenantId.'/'.Str::uuid(),
            'mime_type' => $attributes['mime_type'] ?? null,
            'size_bytes' => $attributes['size_bytes'],
            'expected_sha256' => isset($attributes['sha256']) ? mb_strtolower($attributes['sha256']) : null,
            'original_name' => $attributes['original_name'] ?? null,
            'state' => AttachmentState::Pending,
        ]);
        $attachment->tenant_id = $tenantId;
        $attachment->save();

        return $attachment;
    }
}
