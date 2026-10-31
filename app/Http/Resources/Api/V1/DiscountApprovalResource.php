<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'quote_id' => $this->quote?->public_id,
            'percent' => $this->percent,
            'status' => $this->status->value,
            'requested_by_membership_id' => $this->requestedBy?->public_id,
            'approved_by_membership_id' => $this->approvedBy?->public_id,
            'reason' => $this->reason,
            'decision_reason' => $this->decision_reason,
            'decided_at' => $this->decided_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
