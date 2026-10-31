<?php

namespace App\Modules\Operations\Outbox;

use App\Modules\Operations\Jobs\ProcessOutboxDelivery;
use App\Modules\Operations\Models\EventDelivery;
use Illuminate\Support\Facades\DB;

final class OutboxDispatcher
{
    public function dispatch(?int $limit = null): int
    {
        $limit ??= (int) config('outbox.batch_size', 100);
        $leaseSeconds = (int) config('outbox.lease_seconds', 60);

        $claimed = DB::transaction(function () use ($limit, $leaseSeconds): array {
            $deliveries = EventDelivery::query()
                ->where('next_attempt_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('lease_until')->orWhere('lease_until', '<', now()))
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('consumer_receipts')
                        ->whereColumn('consumer_receipts.tenant_id', 'event_deliveries.tenant_id')
                        ->whereColumn('consumer_receipts.event_id', 'event_deliveries.event_id')
                        ->whereColumn('consumer_receipts.consumer_name', 'event_deliveries.consumer_name');
                })
                ->orderBy('id')
                ->lock('for update skip locked')
                ->limit($limit)
                ->get();

            $leaseUntil = now()->addSeconds($leaseSeconds);

            foreach ($deliveries as $delivery) {
                $delivery->forceFill([
                    'attempts' => $delivery->attempts + 1,
                    'lease_until' => $leaseUntil,
                    'last_dispatched_at' => now(),
                    'next_attempt_at' => $leaseUntil,
                ])->save();
            }

            return $deliveries
                ->map(fn (EventDelivery $delivery): array => [$delivery->id, $delivery->tenant_id])
                ->all();
        });

        foreach ($claimed as [$deliveryId, $tenantId]) {
            ProcessOutboxDelivery::dispatch($deliveryId, $tenantId)->onQueue('operations');
        }

        return count($claimed);
    }
}
