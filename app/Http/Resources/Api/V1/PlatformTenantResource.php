<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->public_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'subscription' => $this->subscription_summary ?? null,
            'counts' => $this->resource_counts ?? null,
        ];
    }
}
