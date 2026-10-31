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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviseDeadlinePromise
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
        string $expectedDueAt,
        ?string $acceptedAt,
        User $actor,
        string $reason,
    ): array {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required when revising a deadline.'],
            ]);
        }

        $expectedDueAt = CarbonImmutable::parse($expectedDueAt)->utc()->format('Y-m-d\TH:i:s\Z');
        $deadline = $this->calculator->handle($branch, $service, $tier, $acceptedAt);

        return DB::transaction(function () use ($branch, $service, $tier, $promisePublicId, $expectedDueAt, $deadline, $actor, $reason): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'deadline-promise:'.$branch->tenant_id.':'.$promisePublicId,
            ]);

            $latest = $this->historyQuery($branch->tenant_id, $promisePublicId)->first();

            if ($latest === null) {
                throw new ApiException('DEADLINE_PROMISE_NOT_FOUND', 'The deadline promise was not found.', 404);
            }

            $currentDueAt = $latest->redacted_changes['due_at'] ?? null;

            if ($currentDueAt !== $expectedDueAt) {
                throw new ApiException('VERSION_CONFLICT', 'The deadline promise changed. Reload it before revising.', 409);
            }

            if ($deadline['due_at'] === $currentDueAt) {
                throw ValidationException::withMessages([
                    'accepted_at' => ['The revision must produce a different due date.'],
                ]);
            }

            $this->auditor->record(
                tenantId: $branch->tenant_id,
                action: AuditAction::DeadlineRevised,
                subjectType: AuditRecorder::SUBJECT_DEADLINE_PROMISE,
                subjectPublicId: $promisePublicId,
                changes: [
                    'branch_id' => $branch->public_id,
                    'service_id' => $service->public_id,
                    'tier' => $tier->value,
                    'accepted_at' => $deadline['accepted_at'],
                    'due_at' => $deadline['due_at'],
                    'old_due_at' => $currentDueAt,
                    'new_due_at' => $deadline['due_at'],
                ],
                actor: $actor,
                reason: $reason,
            );

            return [...$deadline, 'promise_id' => $promisePublicId, 'status' => 'active'];
        });
    }

    /**
     * @return Builder<AuditLog>
     */
    private function historyQuery(int $tenantId, string $promisePublicId)
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('subject_type', AuditRecorder::SUBJECT_DEADLINE_PROMISE)
            ->where('subject_public_id', $promisePublicId)
            ->whereIn('action', [AuditAction::DeadlinePromiseRecorded->value, AuditAction::DeadlineRevised->value]);
    }
}
