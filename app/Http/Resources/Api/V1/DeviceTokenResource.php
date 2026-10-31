<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            'expires_at' => $this->resource['expires_at']->utc()->format('Y-m-d\TH:i:s\Z'),
            'device_id' => $this->resource['device_id'],
        ];
    }
}
