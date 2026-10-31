<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerConsentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'customer_id' => $this->customer?->public_id,
            'purpose' => $this->purpose->value,
            'channel' => $this->channel->value,
            'decision' => $this->decision->value,
            'source' => $this->source,
            'occurred_at' => $this->occurred_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'recorded_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
