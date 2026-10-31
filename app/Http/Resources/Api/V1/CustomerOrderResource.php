<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = $this->invoice?->original_total_rupiah ?? 0;

        return [
            'order_id' => $this->public_id,
            'order_number' => $this->order_number,
            'operational_status' => $this->operational_status->value,
            'invoice_status' => $total === 0 ? 'paid' : 'unpaid',
            'invoice_number' => $this->invoice?->invoice_number,
            'total_rupiah' => $total,
            'paid_rupiah' => 0,
            'balance_due_rupiah' => $total,
            'due_at_original' => $this->due_at_original?->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at_current' => $this->due_at_current?->utc()->format('Y-m-d\TH:i:s\Z'),
            'accepted_at' => $this->accepted_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'branch_id' => $this->salesBranch?->public_id,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
