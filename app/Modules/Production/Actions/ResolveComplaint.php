<?php

namespace App\Modules\Production\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\InvoiceAdjustment;
use App\Modules\Production\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveComplaint
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        Complaint $complaint,
        string $status,
        string $resolution,
        ?string $approvedAdjustmentPublicId,
        int $membershipId,
        ?User $actor = null,
    ): Complaint {
        return DB::transaction(function () use ($complaint, $status, $resolution, $approvedAdjustmentPublicId, $membershipId, $actor): Complaint {
            $locked = Complaint::query()
                ->where('tenant_id', $complaint->tenant_id)
                ->whereKey($complaint->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, ['resolved', 'rejected'], true)) {
                throw new ApiException('COMPLAINT_ALREADY_CLOSED', 'The complaint is already closed.', 409);
            }
            if (! in_array($status, ['resolved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => ['The complaint status must be resolved or rejected.']]);
            }

            $adjustmentId = null;
            if ($approvedAdjustmentPublicId !== null) {
                $adjustment = InvoiceAdjustment::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('public_id', $approvedAdjustmentPublicId)
                    ->first();
                $invoice = $locked->order->invoice;
                if ($adjustment === null || $invoice === null || $adjustment->invoice_id !== $invoice->id) {
                    throw ValidationException::withMessages(['approved_adjustment_id' => ['The adjustment must belong to this order invoice.']]);
                }
                $adjustmentId = $adjustment->id;
            }

            $locked->forceFill([
                'status' => $status,
                'resolution' => $resolution,
                'resolved_by_membership_id' => $membershipId,
                'resolved_at' => now(),
                'approved_adjustment_id' => $adjustmentId,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: $status === 'resolved' ? AuditAction::ComplaintResolved : AuditAction::ComplaintRejected,
                subjectType: AuditRecorder::SUBJECT_COMPLAINT,
                subjectPublicId: $locked->public_id,
                changes: ['status' => $status, 'adjustment_id' => $approvedAdjustmentPublicId],
                reason: $resolution,
                actor: $actor,
            );
            $this->outbox->record(
                tenantId: $locked->tenant_id,
                eventType: 'complaint.'.$status,
                aggregatePublicId: $locked->public_id,
                payload: ['complaint_id' => $locked->public_id, 'status' => $status],
            );

            return $locked->refresh()->load(['order', 'orderLine', 'attachments', 'approvedAdjustment']);
        });
    }
}
