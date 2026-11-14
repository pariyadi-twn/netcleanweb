<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'branch_id' => $this->branch?->public_id,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'current_session' => $this->when(
                $this->relationLoaded('currentSession'),
                fn (): ?CashSessionResource => $this->currentSession === null
                    ? null
                    : CashSessionResource::make($this->currentSession),
            ),
        ];
    }
}
