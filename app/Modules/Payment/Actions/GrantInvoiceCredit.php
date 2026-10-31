<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Models\CreditApproval;
use Illuminate\Support\Facades\DB;

final class GrantInvoiceCredit
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(
        Invoice $invoice,
        int $limitRupiah,
        ?string $expiresAt,
        string $reason,
        int $membershipId,
    ): CreditApproval {
        return DB::transaction(function () use ($invoice, $limitRupiah, $expiresAt, $reason, $membershipId): CreditApproval {
            $approval = CreditApproval::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if ($approval === null) {
                $approval = new CreditApproval([
                    'invoice_id' => $invoice->id,
                    'approved_by_membership_id' => $membershipId,
                    'limit_rupiah' => $limitRupiah,
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                    'status' => 'active',
                ]);
                $approval->tenant_id = $invoice->tenant_id;
                $approval->save();
            } else {
                $approval->forceFill([
                    'approved_by_membership_id' => $membershipId,
                    'limit_rupiah' => $limitRupiah,
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                    'status' => 'active',
                    'version' => $approval->version + 1,
                ])->save();
            }

            $this->auditor->record(
                tenantId: $invoice->tenant_id,
                action: AuditAction::CreditApproved,
                subjectType: AuditRecorder::SUBJECT_INVOICE,
                subjectPublicId: $invoice->public_id,
                changes: ['limit_rupiah' => $limitRupiah, 'expires_at' => $expiresAt],
                reason: $reason,
            );

            return $approval->refresh();
        });
    }
}
