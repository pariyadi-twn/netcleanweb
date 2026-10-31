<?php

namespace App\Modules\Uploads\Actions;

use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TransferAttachment
{
    public function handle(Attachment $attachment, UploadedFile $file, int $membershipId): Attachment
    {
        if ($attachment->uploaded_by_membership_id !== $membershipId) {
            abort(403);
        }

        if ($attachment->state !== AttachmentState::Pending) {
            throw ValidationException::withMessages([
                'attachment' => ['Only pending attachments can receive content.'],
            ]);
        }

        if ($file->getSize() > (int) config('uploads.max_bytes')) {
            throw ValidationException::withMessages([
                'file' => ['The file exceeds the five megabyte limit.'],
            ]);
        }

        Storage::disk((string) config('uploads.disk'))->put(
            $attachment->object_key,
            $file->get(),
            ['visibility' => 'private'],
        );

        $attachment->forceFill([
            'size_bytes' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'version' => $attachment->version + 1,
        ])->save();

        return $attachment->refresh();
    }
}
