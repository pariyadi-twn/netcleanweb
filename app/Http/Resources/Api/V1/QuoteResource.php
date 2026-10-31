<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->pricing_snapshot;

        return [
            'id' => $this->public_id,
            'branch_id' => $this->salesBranch->public_id,
            'customer_id' => $this->customer?->public_id,
            'fulfillment_method' => $this->fulfillment_method->value,
            'pickup_branch_id' => $this->pickupBranch?->public_id,
            'instructions' => $this->instructions,
            'expires_at' => $this->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at' => $this->due_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'total_rupiah' => $this->total_rupiah,
            'totals' => $snapshot['totals'] ?? [],
            'lines' => $snapshot['lines'] ?? [],
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
