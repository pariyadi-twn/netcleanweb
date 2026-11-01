<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'delivery_id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'channel' => $this->channel->value,
            'business_key' => $this->business_key,
            'status' => $this->status->value,
            'provider_reference' => $this->provider_reference,
            'attempts' => $this->attempts,
            'last_error_code' => $this->last_error_code,
            'sent_at' => $this->sent_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
