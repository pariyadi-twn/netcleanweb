<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->public_id,
            'invoice_id' => $this->invoice?->public_id,
            'amount_rupiah' => $this->amount_rupiah,
            'reference' => $this->reference,
            'verified_at' => $this->verified_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'verified_by_user_id' => $this->verified_by_user_id,
        ];
    }
}
