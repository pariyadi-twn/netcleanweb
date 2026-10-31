<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'normalized_phone' => $this->normalized_phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
