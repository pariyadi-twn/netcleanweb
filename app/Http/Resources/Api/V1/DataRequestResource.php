<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data_request_id' => $this->public_id,
            'tenant_id' => $this->whenLoaded('tenant', fn (): string => $this->tenant->public_id),
            'type' => $this->type,
            'status' => $this->status,
            'reason' => $this->reason,
            'customer_id' => $this->customer?->public_id,
            'verification_method' => $this->verification_method,
            'verified_at' => $this->verified_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'rejection_reason' => $this->rejection_reason,
            'result_summary' => $this->result_summary,
            'version' => $this->version,
        ];
    }
}
