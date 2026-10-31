<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'import_id' => $this->public_id,
            'type' => $this->type,
            'source' => $this->source,
            'file_name' => $this->file_name,
            'checksum' => $this->checksum,
            'dry_run' => $this->dry_run,
            'status' => $this->status->value,
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'duplicate_rows' => $this->duplicate_rows,
            'imported_rows' => $this->imported_rows,
            'raw_expires_at' => $this->raw_expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
