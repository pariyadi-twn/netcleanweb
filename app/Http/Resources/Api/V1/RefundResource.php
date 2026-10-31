<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'refund_id' => $this->public_id,
            'payment_id' => $this->payment->public_id,
            'amount_rupiah' => $this->amount_rupiah,
            'status' => $this->status->value,
            'external_ref' => $this->external_ref,
            'reason' => $this->reason,
            'requested_by_membership_id' => $this->requestedBy->public_id,
            'approved_by_membership_id' => $this->approvedBy?->public_id,
            'cash_session_id' => $this->cashSession?->public_id,
            'succeeded_at' => $this->succeeded_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'allocated_refund_rupiah' => (int) $this->allocations->sum('amount_rupiah'),
            'version' => $this->version,
        ];
    }
}
