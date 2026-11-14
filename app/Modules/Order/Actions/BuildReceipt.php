<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderTrackingToken;
use Illuminate\Validation\ValidationException;

final class BuildReceipt
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Order $order, ?string $trackingToken = null): array
    {
        $order->loadMissing(['lines', 'invoice.adjustments', 'invoice.allocations', 'salesBranch', 'pickupBranch', 'quote']);

        $invoice = $order->invoice;
        $invoiceTotal = $invoice?->totalRupiah() ?? 0;
        $invoicePaid = $invoice?->netAllocatedRupiah() ?? 0;
        $lines = $order->lines->map(fn ($line): array => [
            'service_name' => $line->service_snapshot['name'] ?? null,
            'unit' => $line->unit->value,
            'measured_quantity' => $line->measured_quantity,
            'billable_quantity' => $line->billable_quantity,
            'unit_price_rupiah' => $line->unit_price_rupiah,
            'base_total_rupiah' => (int) ($line->service_snapshot['base_total_rupiah'] ?? $line->line_total_rupiah),
            'discount_rupiah' => (int) ($line->service_snapshot['discount_rupiah'] ?? 0),
            'express_surcharge_rupiah' => (int) ($line->service_snapshot['express_surcharge_rupiah'] ?? 0),
            'line_total_rupiah' => $line->line_total_rupiah,
        ])->values()->all();
        $delivery = (int) ($order->quote?->pricing_snapshot['totals']['delivery_fee_rupiah'] ?? 0);
        $branch = $order->pickupBranch ?? $order->salesBranch;

        $core = [
            'receipt_version' => 1,
            'order_id' => $order->public_id,
            'order_number' => $order->order_number,
            'invoice_number' => $invoice?->invoice_number,
            'invoice_id' => $invoice?->public_id,
            'status' => $order->operational_status->value,
            'accepted_at' => $order->accepted_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at' => $order->due_at_current?->utc()->format('Y-m-d\TH:i:s\Z'),
            'branch' => [
                'name' => $branch?->name,
                'phone' => $branch?->phone,
                'address' => $branch?->address,
            ],
            'customer' => [
                'name' => $order->customer_snapshot['name'] ?? 'Walk-in',
                'phone' => $order->customer_snapshot['normalized_phone'] ?? null,
                'address' => $order->customer_snapshot['address'] ?? null,
            ],
            'lines' => $lines,
            'totals' => [
                'service_subtotal_rupiah' => array_sum(array_column($lines, 'base_total_rupiah')),
                'discount_rupiah' => array_sum(array_column($lines, 'discount_rupiah')),
                'express_surcharge_rupiah' => array_sum(array_column($lines, 'express_surcharge_rupiah')),
                'delivery_fee_rupiah' => $delivery,
                'total_rupiah' => $invoiceTotal,
                'paid_rupiah' => $invoicePaid,
                'balance_due_rupiah' => $invoiceTotal - $invoicePaid,
            ],
            'tracking_url' => null,
        ];
        $core['receipt_hash'] = hash('sha256', json_encode($core, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($trackingToken !== null) {
            $token = OrderTrackingToken::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_id', $order->id)
                ->where('token_hash', hash('sha256', $trackingToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($token === null) {
                throw ValidationException::withMessages([
                    'tracking_token' => ['The tracking token is not active for this order.'],
                ]);
            }

            $core['tracking_url'] = url('/api/v1/tracking/'.$trackingToken);
        }

        return $core;
    }
}
