<?php

namespace App\Modules\Identity\Audit;

use App\Models\User;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;
use App\Support\Http\CorrelationId;
use InvalidArgumentException;

final class AuditRecorder
{
    public const SUBJECT_TENANT_MEMBERSHIP = 'tenant_membership';

    public const SUBJECT_ROLE = 'role';

    public const SUBJECT_BRANCH = 'branch';

    public const SUBJECT_SERVICE = 'service';

    public const SUBJECT_DEADLINE_PROMISE = 'deadline_promise';

    public const SUBJECT_ORDER = 'order';

    public const SUBJECT_QUOTE = 'quote';

    public const SUBJECT_PAYMENT = 'payment';

    public const SUBJECT_INVOICE = 'invoice';

    public const SUBJECT_REFUND = 'refund';

    public const SUBJECT_ALLOCATION = 'payment_allocation';

    public const SUBJECT_EXPENSE = 'expense';

    public const SUBJECT_CASH_TRANSFER = 'cash_transfer';

    public const SUBJECT_IMPORT_BATCH = 'import_batch';

    public const SUBJECT_REPORT_EXPORT = 'report_export';

    public const SUBJECT_SUPPORT_GRANT = 'support_grant';

    public const SUBJECT_DATA_REQUEST = 'data_request';

    public const SUBJECT_OUTBOX_EVENT = 'outbox_event';

    private const ALLOWED_SUBJECTS = [self::SUBJECT_TENANT_MEMBERSHIP, self::SUBJECT_ROLE, self::SUBJECT_BRANCH, self::SUBJECT_SERVICE, self::SUBJECT_DEADLINE_PROMISE, self::SUBJECT_ORDER, self::SUBJECT_QUOTE, self::SUBJECT_PAYMENT, self::SUBJECT_INVOICE, self::SUBJECT_REFUND, self::SUBJECT_ALLOCATION, self::SUBJECT_EXPENSE, self::SUBJECT_CASH_TRANSFER, self::SUBJECT_IMPORT_BATCH, self::SUBJECT_REPORT_EXPORT, self::SUBJECT_SUPPORT_GRANT, self::SUBJECT_DATA_REQUEST, self::SUBJECT_OUTBOX_EVENT];

    private const SENSITIVE_KEY_PATTERN = '/pass(word)?|token|secret|mfa|recovery|authorization|api[_-]?key|credential/i';

    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(
        int $tenantId,
        AuditAction $action,
        string $subjectType,
        ?string $subjectPublicId,
        array $changes = [],
        ?User $actor = null,
        ?string $reason = null,
    ): AuditLog {
        if (! in_array($subjectType, self::ALLOWED_SUBJECTS, true)) {
            throw new InvalidArgumentException('Unsupported audit subject type.');
        }

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->id,
            'action' => $action->value,
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'correlation_id' => CorrelationId::current(),
            'redacted_changes' => $this->redact($changes),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function redact(array $changes): array
    {
        $redacted = [];

        foreach ($changes as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }
}
