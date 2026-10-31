<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'service' => [
                'id' => $this->service->public_id,
                'code' => $this->service->code,
                'name' => $this->service->name,
            ],
            'tier' => $this->tier->value,
            'unit_price_rupiah' => $this->unit_price_rupiah,
            'effective_from' => $this->effective_from?->utc()->format('Y-m-d\TH:i:s\Z'),
            'effective_until' => $this->effective_until?->utc()->format('Y-m-d\TH:i:s\Z'),
            'rules' => $this->rules ?? [],
            'version' => $this->version,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
