<?php

namespace App\Modules\Cash\Actions;

use App\Exceptions\ApiException;
use App\Modules\Cash\Enums\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashSessionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseCashSession
{
    public function handle(
        CashSession $session,
        int $countedClosingRupiah,
        ?string $reason,
        int $membershipId,
    ): CashSession {
        return DB::transaction(function () use ($session, $countedClosingRupiah, $reason, $membershipId): CashSession {
            $locked = CashSession::query()
                ->where('tenant_id', $session->tenant_id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CashSessionStatus::Open) {
                throw new ApiException('CASH_SESSION_CLOSED', 'The cash session is already closed.', 409);
            }

            $ledger = (int) $locked->entries()->sum('amount_rupiah');
            $expected = $locked->opening_rupiah + $ledger;
            $difference = $countedClosingRupiah - $expected;

            if ($difference !== 0 && trim((string) $reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required when counted cash differs from the ledger.'],
                ]);
            }

            $locked->forceFill([
                'closed_at' => now(),
                'counted_closing_rupiah' => $countedClosingRupiah,
                'status' => CashSessionStatus::Closed,
                'discrepancy_reason' => $difference === 0 ? null : $reason,
                'version' => $locked->version + 1,
            ])->save();

            $event = new CashSessionEvent([
                'cash_session_id' => $locked->id,
                'event_type' => 'closed',
                'actor_membership_id' => $membershipId,
                'payload' => [
                    'opening_rupiah' => $locked->opening_rupiah,
                    'ledger_rupiah' => $ledger,
                    'expected_rupiah' => $expected,
                    'counted_rupiah' => $countedClosingRupiah,
                    'difference_rupiah' => $difference,
                ],
                'reason' => $reason,
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            return $locked->refresh()->load(['register.branch', 'cashier', 'entries', 'events']);
        });
    }
}
