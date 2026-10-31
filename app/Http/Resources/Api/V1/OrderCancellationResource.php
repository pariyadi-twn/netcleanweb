<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderCancellationResource extends JsonResource
{
    public function __construct(Order $order, private readonly OrderEvent $event)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->invoice;
        $total = $invoice?->totalRupiah() ?? 0;
        $paid = $invoice?->netAllocatedRupiah() ?? 0;

        return [
            'order_id' => $this->public_id,
            'order_number' => $this->order_number,
            'operational_status' => $this->operational_status->value,
            'invoice_id' => $invoice?->public_id,
            'invoice_status' => $invoice?->document_status->value,
            'total_rupiah' => $total,
            'paid_rupiah' => $paid,
            'balance_due_rupiah' => $total - $paid,
            'refund_required_rupiah' => max(0, $paid),
            'reason' => $this->event->reason,
            'cancelled_at' => $this->event->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'bags' => $this->bags->map(fn ($bag): array => [
                'bag_id' => $bag->public_id,
                'custody_status' => $bag->custody_status,
            ])->values()->all(),
            'version' => $this->version,
        ];
    }
}
