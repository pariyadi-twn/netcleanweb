<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderTrackingToken;
use Illuminate\Support\Facades\DB;

final class IssueTrackingToken
{
    /**
     * @return array{token: string, expires_at: string, order_id: string}
     */
    public function handle(Order $order, ?int $membershipId = null): array
    {
        return DB::transaction(function () use ($order, $membershipId): array {
            $locked = Order::query()->where('tenant_id', $order->tenant_id)->whereKey($order->id)->lockForUpdate()->firstOrFail();

            OrderTrackingToken::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('order_id', $locked->id)
                ->whereNull('revoked_at')
                ->get()
                ->each(function (OrderTrackingToken $token): void {
                    $token->forceFill(['revoked_at' => now(), 'version' => $token->version + 1])->save();
                });

            $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $expiresAt = now()->addDays(30);
            $token = new OrderTrackingToken([
                'order_id' => $locked->id,
                'token_hash' => hash('sha256', $raw),
                'expires_at' => $expiresAt,
                'created_by_membership_id' => $membershipId,
            ]);
            $token->tenant_id = $locked->tenant_id;
            $token->save();

            return [
                'token' => $raw,
                'expires_at' => $expiresAt->utc()->format('Y-m-d\TH:i:s\Z'),
                'order_id' => $locked->public_id,
            ];
        });
    }
}
