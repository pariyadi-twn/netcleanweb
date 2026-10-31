<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cash_transfer_id' => $this->public_id,
            'kind' => $this->kind,
            'status' => $this->status,
            'amount_rupiah' => $this->amount_rupiah,
            'from_register_id' => $this->fromRegister?->public_id,
            'to_register_id' => $this->toRegister?->public_id,
            'from_cash_session_id' => $this->fromSession?->public_id,
            'to_cash_session_id' => $this->toSession?->public_id,
            'reference' => $this->reference,
            'reason' => $this->reason,
            'confirmed_by_membership_id' => $this->confirmedBy->public_id,
            'confirmed_at' => $this->confirmed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
