<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderTrackingToken;

final class ResolvePublicTracking
{
    public function __construct(private readonly TenantScope $scope) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $rawToken): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{20,100}$/', $rawToken) !== 1) {
            throw new ApiException('TRACKING_NOT_FOUND', 'Tracking was not found.', 404);
        }

        $hash = hash('sha256', $rawToken);
        $token = $this->scope->publicTracking(
            $hash,
            fn () => OrderTrackingToken::query()->where('token_hash', $hash)->first(),
        );

        if (! $token instanceof OrderTrackingToken || $token->revoked_at !== null || $token->expires_at->isPast()) {
            throw new ApiException('TRACKING_NOT_FOUND', 'Tracking was not found.', 404);
        }

        $data = $this->scope->forTenantId($token->tenant_id, 0, function () use ($token): ?array {
            $order = Order::query()
                ->where('tenant_id', $token->tenant_id)
                ->whereKey($token->order_id)
                ->with(['salesBranch', 'pickupBranch', 'bags'])
                ->first();

            if ($order === null) {
                return null;
            }

            OrderTrackingToken::query()->whereKey($token->id)->update(['last_used_at' => now()]);
            $branch = $order->pickupBranch ?? $order->salesBranch;

            return [
                'order_number' => $order->order_number,
                'status' => $order->operational_status->value,
                'due_at' => $order->due_at_current?->utc()->format('Y-m-d\TH:i:s\Z'),
                'branch' => [
                    'name' => $branch?->name,
                    'phone' => $branch?->phone,
                ],
                'bag_count' => $order->bags->count(),
            ];
        });

        if ($data === null) {
            throw new ApiException('TRACKING_NOT_FOUND', 'Tracking was not found.', 404);
        }

        return $data;
    }
}
