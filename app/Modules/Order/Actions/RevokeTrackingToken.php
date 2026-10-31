<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\OrderTrackingToken;
use Illuminate\Support\Facades\DB;

final class RevokeTrackingToken
{
    public function handle(int $tenantId, int $orderId): int
    {
        return OrderTrackingToken::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'version' => DB::raw('version + 1'),
            ]);
    }
}
