<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BagLabelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bag_id' => $this->public_id,
            'label_code' => $this->label_code,
            'order_id' => $this->order?->public_id,
            'order_number' => $this->order?->order_number,
            'qr_payload' => $this->label_code,
            'contents' => $this->contents->map(fn ($content): array => [
                'service_name' => $content->orderLine?->service_snapshot['name'] ?? null,
                'physical_quantity' => $content->physical_quantity,
            ])->values(),
        ];
    }
}
