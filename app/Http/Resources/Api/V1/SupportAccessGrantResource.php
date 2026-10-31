<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportAccessGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grant_id' => $this->public_id,
            'tenant_id' => $this->whenLoaded('tenant', fn (): string => $this->tenant->public_id),
            'platform_user_id' => $this->platformUser->public_id,
            'case_reference' => $this->case_reference,
            'reason' => $this->reason,
            'allowed_actions' => $this->allowed_actions,
            'expires_at' => $this->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'revoked_at' => $this->revoked_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_used_at' => $this->last_used_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'use_count' => $this->use_count,
            'version' => $this->version,
        ];
    }
}
