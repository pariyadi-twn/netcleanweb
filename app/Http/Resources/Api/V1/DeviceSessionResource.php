<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->resource['device_id'],
            'name' => $this->resource['name'],
            'created_at' => $this->resource['created_at']?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_used_at' => $this->resource['last_used_at']?->utc()->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $this->resource['expires_at']?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
