<?php

namespace App\Modules\Lifecycle\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Lifecycle\Models\DataRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDataRequest
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(int $tenantId, int $membershipId, string $customerPublicId, string $reason, ?User $actor = null): DataRequest
    {
        return DB::transaction(function () use ($tenantId, $membershipId, $customerPublicId, $reason, $actor): DataRequest {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('public_id', $customerPublicId)
                ->first();

            if ($customer === null) {
                throw ValidationException::withMessages(['customer_id' => ['The customer was not found.']]);
            }

            $existing = DataRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('target_customer_id', $customer->id)
                ->whereIn('status', ['requested', 'verified'])
                ->first();
            if ($existing !== null) {
                throw new ApiException('DATA_REQUEST_PENDING', 'A data request is already pending for this customer.', 409);
            }

            $request = new DataRequest([
                'requested_by_membership_id' => $membershipId,
                'type' => 'deletion',
                'status' => 'requested',
                'reason' => $reason,
                'target_customer_id' => $customer->id,
            ]);
            $request->tenant_id = $tenantId;
            $request->save();

            $this->auditor->record(
                tenantId: $tenantId,
                action: AuditAction::DataRequestCreated,
                subjectType: AuditRecorder::SUBJECT_DATA_REQUEST,
                subjectPublicId: $request->public_id,
                changes: ['customer_id' => $customer->public_id],
                reason: $reason,
                actor: $actor,
            );

            return $request->refresh();
        });
    }
}
