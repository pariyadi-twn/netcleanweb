<?php

namespace App\Modules\Lifecycle\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Lifecycle\Models\DataRequest;
use Illuminate\Support\Facades\DB;

final class DecideDataRequest
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(
        DataRequest $request,
        bool $approve,
        string $verificationMethod,
        int $platformUserId,
        ?string $reason = null,
        ?User $actor = null,
    ): DataRequest {
        return DB::transaction(function () use ($request, $approve, $verificationMethod, $platformUserId, $reason, $actor): DataRequest {
            $locked = DataRequest::query()
                ->where('tenant_id', $request->tenant_id)
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'requested') {
                throw new ApiException('DATA_REQUEST_DECISION_NOT_ALLOWED', 'Only a requested data request can be decided.', 409);
            }

            $status = $approve ? 'verified' : 'rejected';
            $locked->forceFill([
                'status' => $status,
                'verification_method' => $verificationMethod,
                'verified_by_user_id' => $platformUserId,
                'verified_at' => now(),
                'rejection_reason' => $approve ? null : $reason,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: $approve ? AuditAction::DataRequestVerified : AuditAction::DataRequestRejected,
                subjectType: AuditRecorder::SUBJECT_DATA_REQUEST,
                subjectPublicId: $locked->public_id,
                changes: ['status' => $status, 'verification_method' => $verificationMethod],
                reason: $reason,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
