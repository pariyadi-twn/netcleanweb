<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'status' => $this->status->value,
            'expected_version' => $this->expected_version,
            'reason' => $this->reason,
            'decision_reason' => $this->decision_reason,
            'difference_rupiah' => $this->difference_rupiah,
            'current_total_rupiah' => $this->current_total_rupiah,
            'new_total_rupiah' => $this->payload['new_total_rupiah'] ?? null,
            'requested_by_membership_id' => $this->requestedBy?->public_id,
            'approved_by_membership_id' => $this->approvedBy?->public_id,
            'invoice_adjustment_id' => $this->adjustment?->public_id,
            'decided_at' => $this->decided_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'applied_at' => $this->applied_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
