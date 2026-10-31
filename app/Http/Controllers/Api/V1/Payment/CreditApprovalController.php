<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\GrantCreditRequest;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Order\Models\Invoice;
use App\Modules\Payment\Actions\GrantInvoiceCredit;
use App\Modules\Payment\Models\CreditApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreditApprovalController extends Controller
{
    public function store(
        GrantCreditRequest $request,
        string $invoice,
        GrantInvoiceCredit $grantCredit,
    ): JsonResponse {
        $context = app(TenantContext::class);
        $invoice = Invoice::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', Str::lower($invoice))
            ->firstOrFail();

        Gate::authorize('create', [CreditApproval::class, $invoice]);

        $approval = $grantCredit->handle(
            $invoice,
            (int) $request->integer('limit_rupiah'),
            $request->input('expires_at'),
            $request->string('reason')->toString(),
            $context->membershipId,
        );

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->public_id,
                'limit_rupiah' => $approval->limit_rupiah,
                'expires_at' => $approval->expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                'reason' => $approval->reason,
                'status' => $approval->status,
                'approved_by' => $approval->approvedBy->public_id ?? null,
            ],
        ]);
    }
}
