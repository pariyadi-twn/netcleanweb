<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyContextResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->resource['tenant_id'],
            'membership_id' => $this->resource['membership_id'],
            'permission_version' => $this->resource['permission_version'],
            'branch_ids' => $this->resource['branch_ids'],
            'location_ids' => $this->resource['location_ids'],
        ];
    }
}
