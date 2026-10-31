<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ledger = (int) $this->entries->sum('amount_rupiah');
        $expected = $this->opening_rupiah + $ledger;

        return [
            'id' => $this->public_id,
            'cash_register_id' => $this->register?->public_id,
            'branch_id' => $this->register?->branch?->public_id,
            'cashier_membership_id' => $this->cashier?->public_id,
            'status' => $this->status->value,
            'opened_at' => $this->opened_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'closed_at' => $this->closed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'opening_rupiah' => $this->opening_rupiah,
            'ledger_rupiah' => $ledger,
            'expected_rupiah' => $expected,
            'counted_closing_rupiah' => $this->counted_closing_rupiah,
            'difference_rupiah' => $this->counted_closing_rupiah === null ? null : $this->counted_closing_rupiah - $expected,
            'discrepancy_reason' => $this->discrepancy_reason,
            'version' => $this->version,
        ];
    }
}
