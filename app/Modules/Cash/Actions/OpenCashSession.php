<?php

namespace App\Modules\Cash\Actions;

use App\Exceptions\ApiException;
use App\Modules\Cash\Enums\CashSessionStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashSessionEvent;
use App\Modules\Operations\Actions\EnsureBranchAcceptsOrders;
use Illuminate\Support\Facades\DB;

final class OpenCashSession
{
    public function __construct(private readonly EnsureBranchAcceptsOrders $branchGuard) {}

    public function handle(CashRegister $register, int $openingRupiah, int $membershipId): CashSession
    {
        return DB::transaction(function () use ($register, $openingRupiah, $membershipId): CashSession {
            $locked = CashRegister::query()
                ->where('tenant_id', $register->tenant_id)
                ->whereKey($register->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_active) {
                throw new ApiException('CASH_REGISTER_INACTIVE', 'The cash register is inactive.', 409);
            }
            $this->branchGuard->handle($locked->branch);

            $existing = CashSession::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('cash_register_id', $locked->id)
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new ApiException('CASH_SESSION_OPEN', 'The cash register already has an open shift.', 409);
            }

            $session = new CashSession([
                'cash_register_id' => $locked->id,
                'cashier_membership_id' => $membershipId,
                'opened_at' => now(),
                'opening_rupiah' => $openingRupiah,
                'status' => CashSessionStatus::Open,
            ]);
            $session->tenant_id = $locked->tenant_id;
            $session->save();

            $event = new CashSessionEvent([
                'cash_session_id' => $session->id,
                'event_type' => 'opened',
                'actor_membership_id' => $membershipId,
                'payload' => ['opening_rupiah' => $openingRupiah],
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            return $session->refresh()->load(['register.branch', 'cashier', 'entries', 'events']);
        });
    }
}
