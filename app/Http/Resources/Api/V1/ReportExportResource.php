<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'export_id' => $this->public_id,
            'report_type' => $this->report_type,
            'filters' => $this->filters,
            'status' => $this->status->value,
            'row_count' => $this->row_count,
            'file_size_bytes' => $this->file_size_bytes,
            'expires_at' => $this->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'error_code' => $this->error_code,
            'version' => $this->version,
        ];
    }
}
