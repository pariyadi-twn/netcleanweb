<?php

namespace App\Console\Commands;

use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('attachments:cleanup {--dry-run}')]
#[Description('Remove expired orphan uploads without touching linked attachments')]
class CleanupAttachments extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk((string) config('uploads.disk'));
        $pendingBefore = now()->subHours((int) config('uploads.pending_ttl_hours'));
        $quarantineBefore = now()->subDays((int) config('uploads.quarantine_ttl_days'));
        $removed = 0;

        $attachments = Attachment::query()
            ->where(function ($query) use ($pendingBefore, $quarantineBefore): void {
                $query->where(function ($pending) use ($pendingBefore): void {
                    $pending->where('state', AttachmentState::Pending->value)
                        ->where('created_at', '<=', $pendingBefore);
                })->orWhere(function ($quarantined) use ($quarantineBefore): void {
                    $quarantined->where('state', AttachmentState::Quarantined->value)
                        ->where('updated_at', '<=', $quarantineBefore);
                });
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('order_attachments')
                    ->whereColumn('order_attachments.tenant_id', 'attachments.tenant_id')
                    ->whereColumn('order_attachments.attachment_id', 'attachments.id');
            })
            ->get();

        foreach ($attachments as $attachment) {
            if ($this->option('dry-run')) {
                $this->line('Would clean '.$attachment->public_id);

                continue;
            }

            $disk->delete(array_filter([$attachment->object_key, $attachment->thumbnail_key]));
            $attachment->forceFill([
                'state' => AttachmentState::Rejected,
                'thumbnail_key' => null,
                'version' => $attachment->version + 1,
            ])->save();
            $removed++;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : "Cleaned {$removed} attachment(s).");

        return self::SUCCESS;
    }
}
