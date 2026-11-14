<?php

namespace App\Modules\Operations\Outbox;

use App\Modules\Operations\Jobs\ProcessOutboxDelivery;
use Illuminate\Support\Facades\DB;

final class OutboxDispatcher
{
    public function dispatch(?int $limit = null): int
    {
        $limit ??= (int) config('outbox.batch_size', 100);
        $limit = min(max($limit, 0), 1000);

        if ($limit === 0) {
            return 0;
        }

        $leaseSeconds = min(max((int) config('outbox.lease_seconds', 60), 1), 3600);
        $claimed = DB::select(
            'SELECT delivery_id, tenant_id FROM public.claim_outbox_deliveries(?, ?)',
            [$limit, $leaseSeconds],
        );

        foreach ($claimed as $delivery) {
            ProcessOutboxDelivery::dispatch((int) $delivery->delivery_id, (int) $delivery->tenant_id)
                ->onQueue('operations');
        }

        return count($claimed);
    }
}
