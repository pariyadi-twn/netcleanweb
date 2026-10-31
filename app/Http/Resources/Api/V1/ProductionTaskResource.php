<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $content = $this->content;
        $line = $content?->orderLine;
        $order = $content?->order;
        $compact = (bool) $request->boolean('compact');

        $data = [
            'task_id' => $this->public_id,
            'order_id' => $order?->public_id,
            'order_number' => $order?->order_number,
            'bag_id' => $content?->bag?->public_id,
            'bag_content_id' => $content?->public_id,
            'service_name' => $line?->service_snapshot['name'] ?? null,
            'step_code' => $this->step_code,
            'sequence' => $this->sequence,
            'attempt_no' => $this->attempt_no,
            'status' => $this->status->value,
            'due_at' => $this->due_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'overdue' => $this->due_at?->isPast() && $this->status->value !== 'done',
            'version' => $this->version,
        ];

        if ($compact) {
            return $data;
        }

        return [
            ...$data,
            'location_id' => $this->location?->public_id,
            'is_required' => $this->is_required,
            'requires_qc' => $this->requires_qc,
            'express' => (int) ($line?->service_snapshot['express_surcharge_rupiah'] ?? 0) > 0,
            'started_at' => $this->started_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
