<?php

namespace App\Modules\Uploads\Actions;

use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Facades\Storage;

final class AttachmentUrl
{
    public function handle(Attachment $attachment): string
    {
        if ($attachment->state !== AttachmentState::Ready) {
            abort(404);
        }

        return Storage::disk((string) config('uploads.disk'))->temporaryUrl(
            $attachment->object_key,
            now()->addMinutes((int) config('uploads.signed_url_minutes')),
            ['visibility' => 'private'],
        );
    }
}
