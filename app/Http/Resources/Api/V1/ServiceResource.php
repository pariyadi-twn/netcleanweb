<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit->value,
            'minimum_quantity' => $this->minimum_quantity,
            'quantity_step' => $this->quantity_step,
            'is_active' => $this->is_active,
            'sla_mode' => $this->sla_mode->value,
            'regular_turnaround' => $this->regular_turnaround,
            'express_turnaround' => $this->express_turnaround,
            'version' => $this->version,
            'workflow_template' => new WorkflowTemplateResource($this->whenLoaded('workflowTemplate')),
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
