<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
