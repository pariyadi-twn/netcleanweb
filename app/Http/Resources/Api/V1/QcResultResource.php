<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QcResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'qc_result_id' => $this->public_id,
            'bag_content_id' => $this->content?->public_id,
            'production_task_id' => $this->task?->public_id,
            'outcome' => $this->outcome,
            'checklist' => $this->checklist,
            'notes' => $this->notes,
            'attempt_no' => $this->attempt_no,
            'recorded_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
