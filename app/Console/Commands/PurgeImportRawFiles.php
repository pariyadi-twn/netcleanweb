<?php

namespace App\Console\Commands;

use App\Modules\Reporting\Models\ImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('imports:purge {--dry-run}')]
#[Description('Delete raw import files after the configured retention period')]
class PurgeImportRawFiles extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk((string) config('uploads.disk'));
        $batches = ImportBatch::query()
            ->whereNotNull('raw_object_key')
            ->where('raw_expires_at', '<=', now())
            ->get();

        $removed = 0;
        foreach ($batches as $batch) {
            if ($this->option('dry-run')) {
                $this->line('Would purge '.$batch->public_id);

                continue;
            }

            $disk->delete($batch->raw_object_key);
            $batch->forceFill([
                'raw_object_key' => null,
                'raw_purged_at' => now(),
                'version' => $batch->version + 1,
            ])->save();
            $removed++;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : "Purged {$removed} raw import file(s).");

        return self::SUCCESS;
    }
}
