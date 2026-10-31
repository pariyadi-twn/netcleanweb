<?php

namespace App\Modules\Cash\Actions;

use App\Exceptions\ApiException;
use App\Modules\Cash\Enums\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashSessionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReopenCashSession
{
    public function handle(CashSession $session, string $reason, int $membershipId): CashSession
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['A reason is required to reopen a cash session.']]);
        }

        return DB::transaction(function () use ($session, $reason, $membershipId): CashSession {
            $locked = CashSession::query()
                ->where('tenant_id', $session->tenant_id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CashSessionStatus::Closed) {
                throw new ApiException('CASH_SESSION_OPEN', 'Only a closed cash session can be reopened.', 409);
            }

            $open = CashSession::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('cash_register_id', $locked->cash_register_id)
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate()
                ->exists();

            if ($open) {
                throw new ApiException('CASH_SESSION_OPEN', 'The cash register already has an open shift.', 409);
            }

            $old = $locked->only(['opened_at', 'closed_at', 'counted_closing_rupiah', 'discrepancy_reason']);
            $locked->forceFill([
                'closed_at' => null,
                'counted_closing_rupiah' => null,
                'discrepancy_reason' => null,
                'status' => CashSessionStatus::Open,
                'version' => $locked->version + 1,
            ])->save();

            $event = new CashSessionEvent([
                'cash_session_id' => $locked->id,
                'event_type' => 'reopened',
                'actor_membership_id' => $membershipId,
                'payload' => ['previous_closure' => $old],
                'reason' => $reason,
            ]);
            $event->tenant_id = $locked->tenant_id;
            $event->save();

            return $locked->refresh()->load(['register.branch', 'cashier', 'entries', 'events']);
        });
    }
}
