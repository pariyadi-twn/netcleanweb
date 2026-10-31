<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationReversalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reversal_id' => $this->public_id,
            'allocation_id' => $this->paymentAllocation->public_id,
            'invoice_id' => $this->paymentAllocation->invoice->public_id,
            'amount_rupiah' => $this->amount_rupiah,
            'reason' => $this->reason,
            'approved_by_membership_id' => $this->approvedBy->public_id,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
