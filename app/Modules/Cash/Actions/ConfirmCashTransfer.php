<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Models\CashEntry;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmCashTransfer
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  array{kind: string, amount_rupiah: int, from_register_id?: string|null, to_register_id?: string|null, from_cash_session_id?: string|null, to_cash_session_id?: string|null, reason: string, reference?: string|null}  $attributes
     */
    public function handle(array $attributes, int $membershipId, ?User $actor = null): CashTransfer
    {
        return DB::transaction(function () use ($attributes, $membershipId, $actor): CashTransfer {
            $kind = (string) $attributes['kind'];
            $fromRegister = null;
            $toRegister = null;
            $fromSession = null;
            $toSession = null;

            if (! empty($attributes['from_register_id'])) {
                $fromRegister = CashRegister::query()
                    ->where('tenant_id', $this->context->tenantId)
                    ->where('public_id', $attributes['from_register_id'])
                    ->with('branch')
                    ->first();
                if ($fromRegister === null) {
                    throw ValidationException::withMessages(['from_register_id' => ['The source register was not found.']]);
                }
                if (! $this->context->canAccessBranch($fromRegister->branch)) {
                    throw new AuthorizationException('This action is unauthorized.');
                }
            }

            if (! empty($attributes['to_register_id'])) {
                $toRegister = CashRegister::query()
                    ->where('tenant_id', $this->context->tenantId)
                    ->where('public_id', $attributes['to_register_id'])
                    ->with('branch')
                    ->first();
                if ($toRegister === null) {
                    throw ValidationException::withMessages(['to_register_id' => ['The destination register was not found.']]);
                }
                if (! $this->context->canAccessBranch($toRegister->branch)) {
                    throw new AuthorizationException('This action is unauthorized.');
                }
            }

            if ($kind === 'inter_register' && ($fromRegister === null || $toRegister === null || $fromRegister->id === $toRegister->id)) {
                throw ValidationException::withMessages(['to_register_id' => ['An inter-register transfer requires two different registers.']]);
            }
            if ($kind === 'bank_deposit' && ($fromRegister === null || $toRegister !== null)) {
                throw ValidationException::withMessages(['from_register_id' => ['A bank deposit requires only a source register.']]);
            }
            if ($kind === 'owner_funding' && ($fromRegister !== null || $toRegister === null)) {
                throw ValidationException::withMessages(['to_register_id' => ['Owner funding requires only a destination register.']]);
            }

            $sessionIds = collect([$attributes['from_cash_session_id'] ?? null, $attributes['to_cash_session_id'] ?? null])
                ->filter()
                ->unique()
                ->values();
            $sessions = CashSession::query()
                ->where('tenant_id', $this->context->tenantId)
                ->whereIn('public_id', $sessionIds)
                ->where('status', 'open')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            if (! empty($attributes['from_cash_session_id'])) {
                $fromSession = $sessions->get($attributes['from_cash_session_id']);
                if ($fromSession === null || $fromRegister === null || $fromSession->cash_register_id !== $fromRegister->id) {
                    throw ValidationException::withMessages(['from_cash_session_id' => ['An open source cash session is required.']]);
                }
            }
            if (! empty($attributes['to_cash_session_id'])) {
                $toSession = $sessions->get($attributes['to_cash_session_id']);
                if ($toSession === null || $toRegister === null || $toSession->cash_register_id !== $toRegister->id) {
                    throw ValidationException::withMessages(['to_cash_session_id' => ['An open destination cash session is required.']]);
                }
            }

            if ($kind === 'inter_register' && ($fromSession === null || $toSession === null)) {
                throw ValidationException::withMessages(['from_cash_session_id' => ['Both open cash sessions are required for an inter-register transfer.']]);
            }
            if ($kind === 'bank_deposit' && $fromSession === null) {
                throw ValidationException::withMessages(['from_cash_session_id' => ['An open source cash session is required.']]);
            }
            if ($kind === 'owner_funding' && $toSession === null) {
                throw ValidationException::withMessages(['to_cash_session_id' => ['An open destination cash session is required.']]);
            }

            $transfer = new CashTransfer([
                'from_register_id' => $fromRegister?->id,
                'to_register_id' => $toRegister?->id,
                'from_cash_session_id' => $fromSession?->id,
                'to_cash_session_id' => $toSession?->id,
                'amount_rupiah' => (int) $attributes['amount_rupiah'],
                'kind' => $kind,
                'status' => 'confirmed',
                'reference' => $attributes['reference'] ?? null,
                'reason' => (string) $attributes['reason'],
                'requested_by_membership_id' => $membershipId,
                'confirmed_by_membership_id' => $membershipId,
                'confirmed_at' => now(),
            ]);
            $transfer->tenant_id = $this->context->tenantId;
            $transfer->save();

            if ($fromSession !== null) {
                $this->entry($transfer, $fromSession, 'transfer_out', -(int) $attributes['amount_rupiah']);
            }
            if ($toSession !== null) {
                $this->entry($transfer, $toSession, 'transfer_in', (int) $attributes['amount_rupiah']);
            }

            $this->auditor->record(
                tenantId: $transfer->tenant_id,
                action: AuditAction::CashTransferConfirmed,
                subjectType: AuditRecorder::SUBJECT_CASH_TRANSFER,
                subjectPublicId: $transfer->public_id,
                changes: ['kind' => $kind, 'amount_rupiah' => $transfer->amount_rupiah],
                reason: $transfer->reason,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $transfer->tenant_id,
                eventType: 'cash_transfer.confirmed',
                aggregatePublicId: $transfer->public_id,
                payload: [
                    'cash_transfer_id' => $transfer->public_id,
                    'kind' => $kind,
                    'amount_rupiah' => $transfer->amount_rupiah,
                ],
            );

            return $transfer->refresh();
        });
    }

    private function entry(CashTransfer $transfer, CashSession $session, string $kind, int $amount): void
    {
        $entry = new CashEntry([
            'cash_session_id' => $session->id,
            'kind' => $kind,
            'amount_rupiah' => $amount,
            'reference_type' => 'cash_transfer',
            'reference_id' => $transfer->id,
        ]);
        $entry->tenant_id = $transfer->tenant_id;
        $entry->save();
    }
}
