<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->invoice;
        $total = $invoice?->totalRupiah() ?? 0;
        $paid = $invoice?->netAllocatedRupiah() ?? 0;
        $invoiceStatus = $invoice === null || $total === 0
            ? 'paid'
            : ($paid <= 0 ? 'unpaid' : ($paid === $total ? 'paid' : ($paid < $total ? 'partially_paid' : 'overallocated')));

        return [
            'order_id' => $this->public_id,
            'order_number' => $this->order_number,
            'operational_status' => $this->operational_status->value,
            'invoice_status' => $invoiceStatus,
            'invoice_number' => $invoice?->invoice_number,
            'total_rupiah' => $total,
            'paid_rupiah' => $paid,
            'balance_due_rupiah' => $total - $paid,
            'quote_id' => $this->quote->public_id,
            'branch_id' => $this->salesBranch->public_id,
            'due_at_original' => $this->due_at_original?->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at_current' => $this->due_at_current?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
            'lines' => $this->lines->map(fn ($line): array => [
                'id' => $line->public_id,
                'service_id' => $line->service_snapshot['id'] ?? null,
                'service_name' => $line->service_snapshot['name'] ?? null,
                'unit' => $line->unit->value,
                'measured_quantity' => $line->measured_quantity,
                'billable_quantity' => $line->billable_quantity,
                'unit_price_rupiah' => $line->unit_price_rupiah,
                'line_total_rupiah' => $line->line_total_rupiah,
            ])->values()->all(),
        ];
    }
}
