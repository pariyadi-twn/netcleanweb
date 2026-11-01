<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'complaint_id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'order_line_id' => $this->orderLine?->public_id,
            'kind' => $this->kind,
            'status' => $this->status,
            'description' => $this->description,
            'resolution' => $this->resolution,
            'approved_adjustment_id' => $this->approvedAdjustment?->public_id,
            'rewash_attempt_no' => $this->rewash_attempt_no,
            'attachment_ids' => $this->attachments->pluck('public_id')->values()->all(),
            'opened_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'resolved_at' => $this->resolved_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
