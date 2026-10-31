<?php

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Order\Enums\ReceiptPrintStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\ReceiptPrintAttempt;
use Illuminate\Support\Facades\DB;

final class RecordReceiptPrint
{
    public function __construct(private readonly BuildReceipt $receipts) {}

    public function handle(
        Order $order,
        string $outcome,
        ?string $errorCode,
        ?string $trackingToken,
        int $membershipId,
        ?User $actor = null,
    ): ReceiptPrintAttempt {
        $receipt = $this->receipts->handle($order, $trackingToken);

        return DB::transaction(function () use ($order, $outcome, $errorCode, $receipt, $membershipId): ReceiptPrintAttempt {
            $attempt = new ReceiptPrintAttempt([
                'order_id' => $order->id,
                'actor_membership_id' => $membershipId,
                'status' => $outcome === 'printed' ? ReceiptPrintStatus::Printed : ReceiptPrintStatus::Failed,
                'receipt_hash' => $receipt['receipt_hash'],
                'error_code' => $outcome === 'printed' ? null : $errorCode,
            ]);
            $attempt->tenant_id = $order->tenant_id;
            $attempt->save();

            return $attempt->refresh();
        });
    }
}
