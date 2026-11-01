<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'handover_id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'location_id' => $this->location?->public_id,
            'recipient_name' => $this->recipient_name,
            'verification_method' => $this->verification_method,
            'credit_approval_id' => $this->creditApproval?->public_id,
            'completed_at' => $this->completed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'lines' => $this->lines->map(fn ($line): array => [
                'handover_line_id' => $line->public_id,
                'bag_content_id' => $line->content?->public_id,
                'quantity' => $line->quantity,
            ])->values()->all(),
            'version' => $this->version,
        ];
    }
}
