<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'scope' => $this->branch_id === null ? 'tenant' : 'branch',
            'branch_id' => $this->branch_id === null ? null : $this->branch?->public_id,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'entries' => PriceEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
