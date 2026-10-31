<?php

namespace App\Modules\Lifecycle\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Lifecycle\Models\DataDeletionTombstone;
use App\Modules\Lifecycle\Models\DataRequest;
use Illuminate\Support\Facades\DB;

final class ProcessDataDeletion
{
    public function __construct(
        private readonly AnonymizeCustomer $anonymize,
        private readonly AuditRecorder $auditor,
    ) {}

    public function handle(DataRequest $request, int $platformUserId, ?User $actor = null): DataRequest
    {
        return DB::transaction(function () use ($request, $platformUserId, $actor): DataRequest {
            $locked = DataRequest::query()
                ->where('tenant_id', $request->tenant_id)
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'verified') {
                throw new ApiException('DATA_REQUEST_NOT_VERIFIED', 'The data request must be verified before processing.', 409);
            }
            $customer = $locked->customer;
            if ($customer === null) {
                throw new ApiException('DATA_REQUEST_TARGET_MISSING', 'The target customer no longer exists.', 409);
            }

            $summary = $this->anonymize->handle($customer);

            $tombstone = DataDeletionTombstone::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('subject_type', 'customer')
                ->where('subject_public_id', $customer->public_id)
                ->first();
            if ($tombstone === null) {
                $tombstone = new DataDeletionTombstone([
                    'data_request_id' => $locked->id,
                    'subject_type' => 'customer',
                    'subject_public_id' => $customer->public_id,
                    'reason' => $locked->reason,
                    'applied_at' => now(),
                ]);
                $tombstone->tenant_id = $locked->tenant_id;
                $tombstone->save();
            }

            $locked->forceFill([
                'status' => 'completed',
                'processed_by_user_id' => $platformUserId,
                'completed_at' => now(),
                'result_summary' => [
                    'customer_id' => $customer->public_id,
                    'anonymized_addresses' => $summary['addresses'],
                    'tombstone_id' => $tombstone->public_id,
                ],
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::DataRequestCompleted,
                subjectType: AuditRecorder::SUBJECT_DATA_REQUEST,
                subjectPublicId: $locked->public_id,
                changes: [
                    'customer_id' => $customer->public_id,
                    'tombstone_id' => $tombstone->public_id,
                ],
                reason: $locked->reason,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
