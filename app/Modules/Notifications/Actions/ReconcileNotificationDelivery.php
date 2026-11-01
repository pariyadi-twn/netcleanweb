<?php

namespace App\Modules\Notifications\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReconcileNotificationDelivery
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(
        NotificationDelivery $delivery,
        string $status,
        ?string $providerReference,
        ?string $reason,
        ?User $actor = null,
    ): NotificationDelivery {
        return DB::transaction(function () use ($delivery, $status, $providerReference, $reason, $actor): NotificationDelivery {
            $locked = NotificationDelivery::query()
                ->where('tenant_id', $delivery->tenant_id)
                ->whereKey($delivery->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [NotificationStatus::Sent], true)) {
                throw new ApiException('NOTIFICATION_ALREADY_SENT', 'A sent notification cannot be reconciled again.', 409);
            }
            if (! in_array($status, ['sent', 'failed'], true)) {
                throw ValidationException::withMessages(['status' => ['Status must be sent or failed.']]);
            }
            if ($status === 'sent' && trim((string) $providerReference) === '') {
                throw ValidationException::withMessages(['provider_reference' => ['A provider reference is required for a sent notification.']]);
            }

            $target = NotificationStatus::from($status);
            $locked->forceFill([
                'status' => $target,
                'provider_reference' => $providerReference,
                'last_error_code' => $target === NotificationStatus::Failed ? 'RECONCILED_FAILED' : null,
                'next_attempt_at' => null,
                'sent_at' => $target === NotificationStatus::Sent ? now() : null,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::NotificationReconciled,
                subjectType: AuditRecorder::SUBJECT_NOTIFICATION_DELIVERY,
                subjectPublicId: $locked->public_id,
                changes: ['status' => $status, 'provider_reference' => $providerReference],
                reason: $reason,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
