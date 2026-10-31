<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'state' => $this->state->value,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'width' => $this->width,
            'height' => $this->height,
            'original_name' => $this->original_name,
            'has_thumbnail' => $this->thumbnail_key !== null,
            'version' => $this->version,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
