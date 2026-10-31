<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Uploads\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class OrderConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ids = DB::table('order_attachments')
            ->where('tenant_id', $this->tenant_id)
            ->where('order_id', $this->order_id)
            ->where('purpose', 'initial_condition')
            ->pluck('attachment_id');
        $attachments = Attachment::query()
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        return [
            'id' => $this->public_id,
            'order_id' => $this->order?->public_id,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'recorded_by_membership_id' => $this->recordedBy?->public_id,
            'version' => $this->version,
            'attachments' => AttachmentResource::collection($attachments)->resolve(),
        ];
    }
}
