<?php

namespace App\Modules\Uploads\Actions;

use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class FinalizeAttachment
{
    public function handle(Attachment $attachment, int $membershipId): Attachment
    {
        if ($attachment->uploaded_by_membership_id !== $membershipId) {
            abort(403);
        }

        if ($attachment->state !== AttachmentState::Pending) {
            throw ValidationException::withMessages([
                'attachment' => ['Only pending attachments can be finalized.'],
            ]);
        }

        $disk = Storage::disk((string) config('uploads.disk'));
        if (! $disk->exists($attachment->object_key)) {
            $this->reject($attachment, 'The uploaded content is missing.', quarantine: false);
        }

        $bytes = $disk->get($attachment->object_key);
        $size = strlen($bytes);
        if ($size > (int) config('uploads.max_bytes')) {
            $this->quarantine($attachment, 'The uploaded content exceeds the five megabyte limit.');
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info['mime'], config('uploads.allowed_mimes'), true)) {
            $this->quarantine($attachment, 'Only JPEG, PNG, or WebP images are allowed.');
        }
        if ($info[0] <= 0 || $info[1] <= 0 || $info[0] > (int) config('uploads.max_dimension') || $info[1] > (int) config('uploads.max_dimension')) {
            $this->quarantine($attachment, 'The image dimensions are outside the allowed range.');
        }

        $sha256 = hash('sha256', $bytes);
        if ($attachment->expected_sha256 !== null && ! hash_equals($attachment->expected_sha256, $sha256)) {
            $this->quarantine($attachment, 'The checksum does not match the uploaded content.');
        }

        $thumbnailKey = $this->thumbnail($bytes, (int) $attachment->tenant_id);
        $attachment->forceFill([
            'state' => AttachmentState::Ready,
            'mime_type' => $info['mime'],
            'size_bytes' => $size,
            'sha256' => $sha256,
            'width' => $info[0],
            'height' => $info[1],
            'thumbnail_key' => $thumbnailKey,
            'version' => $attachment->version + 1,
        ])->save();

        return $attachment->refresh();
    }

    private function quarantine(Attachment $attachment, string $message): never
    {
        $disk = Storage::disk((string) config('uploads.disk'));
        $quarantineKey = 'quarantine/'.$attachment->tenant_id.'/'.basename($attachment->object_key);
        if ($disk->exists($attachment->object_key)) {
            $disk->copy($attachment->object_key, $quarantineKey);
            $disk->delete($attachment->object_key);
        }

        $attachment->forceFill([
            'state' => AttachmentState::Quarantined,
            'object_key' => $quarantineKey,
            'thumbnail_key' => null,
            'version' => $attachment->version + 1,
        ])->save();

        throw ValidationException::withMessages(['file' => [$message]]);
    }

    private function reject(Attachment $attachment, string $message, bool $quarantine = false): never
    {
        if ($quarantine) {
            $this->quarantine($attachment, $message);
        }

        $attachment->forceFill([
            'state' => AttachmentState::Rejected,
            'version' => $attachment->version + 1,
        ])->save();

        throw ValidationException::withMessages(['file' => [$message]]);
    }

    private function thumbnail(string $bytes, int $tenantId): ?string
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = (int) config('uploads.thumbnail_max');
        $scale = min(1, $max / max($width, $height));
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        ob_start();
        imagejpeg($thumb, null, 85);
        $thumbnailBytes = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumb);

        $key = 'thumbnails/'.$tenantId.'/'.bin2hex(random_bytes(16)).'.jpg';
        Storage::disk((string) config('uploads.disk'))->put($key, $thumbnailBytes, ['visibility' => 'private']);

        return $key;
    }
}
