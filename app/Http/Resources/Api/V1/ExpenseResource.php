<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'expense_id' => $this->public_id,
            'branch_id' => $this->branch->public_id,
            'category' => $this->category,
            'description' => $this->description,
            'amount_rupiah' => $this->amount_rupiah,
            'occurred_at' => $this->occurred_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'approval_status' => $this->approval_status->value,
            'cash_session_id' => $this->cashSession?->public_id,
            'requested_by_membership_id' => $this->requestedBy->public_id,
            'approved_by_membership_id' => $this->approvedBy?->public_id,
            'approved_at' => $this->approved_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
