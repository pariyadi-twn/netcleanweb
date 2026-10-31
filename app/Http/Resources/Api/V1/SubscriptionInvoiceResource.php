<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this->public_id,
            'invoice_number' => $this->platform_invoice_number,
            'subscription_id' => $this->subscription?->public_id,
            'kind' => $this->kind,
            'amount_rupiah' => $this->amount_rupiah,
            'status' => $this->status->value,
            'period_start' => $this->period_start?->utc()->format('Y-m-d\TH:i:s\Z'),
            'period_end' => $this->period_end?->utc()->format('Y-m-d\TH:i:s\Z'),
            'issued_at' => $this->issued_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at' => $this->due_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
