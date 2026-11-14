<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contents = $this->contents->map(fn ($content): array => [
            'order_line_id' => $content->orderLine?->public_id,
            'service_name' => $content->orderLine?->service_snapshot['name'] ?? null,
            'physical_quantity' => $content->physical_quantity,
            'handed_over_quantity' => $content->handed_over_quantity,
            'is_active' => $content->is_active,
        ])->values();

        return [
            'id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'label_code' => $this->label_code,
            'custody_status' => $this->custody_status,
            'current_location_id' => $this->currentLocation?->public_id,
            'contents' => $contents,
            'total_physical_quantity' => $contents->sum(fn (array $content): float => (float) $content['physical_quantity']),
            'version' => $this->version,
        ];
    }
}
