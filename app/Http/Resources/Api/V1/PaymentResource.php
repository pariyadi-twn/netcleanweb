<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->allocations->first()?->invoice;
        $total = $invoice?->totalRupiah() ?? 0;
        $paid = $invoice?->netAllocatedRupiah() ?? 0;
        $balance = $total - $paid;
        $invoiceStatus = $invoice === null || $total === 0
            ? 'paid'
            : ($paid <= 0 ? 'unpaid' : ($paid === $total ? 'paid' : ($paid < $total ? 'partially_paid' : 'overallocated')));

        return [
            'payment_id' => $this->public_id,
            'invoice_id' => $invoice?->public_id,
            'method' => $this->method,
            'amount_rupiah' => $this->amount_rupiah,
            'allocated_rupiah' => (int) $this->allocations->sum('amount_rupiah'),
            'unallocated_rupiah' => $this->unallocated_rupiah,
            'cash_received_rupiah' => $this->cash_received_rupiah,
            'change_rupiah' => $this->change_rupiah,
            'received_at' => $this->received_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'invoice_status' => $invoiceStatus,
            'total_rupiah' => $total,
            'paid_rupiah' => $paid,
            'balance_due_rupiah' => $balance,
        ];
    }
}
