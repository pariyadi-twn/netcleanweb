<?php

namespace App\Modules\Operations\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecordDeadlinePromise
{
    public function __construct(
        private readonly CalculateDeadline $calculator,
        private readonly AuditRecorder $auditor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        Branch $branch,
        Service $service,
        PriceTier $tier,
        string $promisePublicId,
        ?string $acceptedAt = null,
        ?User $actor = null,
        ?string $reason = null,
    ): array {
        if (! Str::isUuid($promisePublicId)) {
            throw ValidationException::withMessages([
                'promise_id' => ['The promise ID must be a valid UUID.'],
            ]);
        }

        $deadline = $this->calculator->handle($branch, $service, $tier, $acceptedAt);

        return DB::transaction(function () use ($branch, $service, $tier, $promisePublicId, $deadline, $actor, $reason): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'deadline-promise:'.$branch->tenant_id.':'.$promisePublicId,
            ]);

            $exists = AuditLog::query()
                ->where('tenant_id', $branch->tenant_id)
                ->where('subject_type', AuditRecorder::SUBJECT_DEADLINE_PROMISE)
                ->where('subject_public_id', $promisePublicId)
                ->exists();

            if ($exists) {
                throw new ApiException('DEADLINE_PROMISE_EXISTS', 'The deadline promise already exists.', 409);
            }

            $this->auditor->record(
                tenantId: $branch->tenant_id,
                action: AuditAction::DeadlinePromiseRecorded,
                subjectType: AuditRecorder::SUBJECT_DEADLINE_PROMISE,
                subjectPublicId: $promisePublicId,
                changes: [
                    'branch_id' => $branch->public_id,
                    'service_id' => $service->public_id,
                    'tier' => $tier->value,
                    'accepted_at' => $deadline['accepted_at'],
                    'due_at' => $deadline['due_at'],
                    'mode' => $deadline['mode'],
                    'turnaround' => $deadline['turnaround'],
                ],
                actor: $actor,
                reason: $reason,
            );

            return [...$deadline, 'promise_id' => $promisePublicId, 'status' => 'active'];
        });
    }
}
