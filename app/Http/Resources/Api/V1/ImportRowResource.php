<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'row_id' => $this->public_id,
            'row_number' => $this->row_number,
            'status' => $this->status->value,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'target_public_id' => $this->target_public_id,
            'normalized_payload' => $this->normalized_payload,
        ];
    }
}
