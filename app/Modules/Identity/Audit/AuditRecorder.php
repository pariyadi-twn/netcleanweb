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

    public const SUBJECT_OUTBOX_EVENT = 'outbox_event';

    private const ALLOWED_SUBJECTS = [self::SUBJECT_TENANT_MEMBERSHIP, self::SUBJECT_ROLE, self::SUBJECT_BRANCH, self::SUBJECT_SERVICE, self::SUBJECT_DEADLINE_PROMISE, self::SUBJECT_ORDER, self::SUBJECT_OUTBOX_EVENT];

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
