<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\ReceivablesReportRequest;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Actions\OutstandingInvoices;
use App\Support\Http\CorrelationId;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReceivablesReportController extends Controller
{
    public function __invoke(
        ReceivablesReportRequest $request,
        OutstandingInvoices $outstandingInvoices,
    ): JsonResponse {
        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id'))
            ->firstOrFail();

        Gate::authorize('viewReports', $branch);

        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $asOfDate = $request->input('as_of')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('as_of')->toString(), $timezone)
            : CarbonImmutable::now($timezone)->startOfDay();
        $asOf = $asOfDate->endOfDay();
        $paginator = $outstandingInvoices->paginate(
            $branch,
            $asOf,
            (int) $request->integer('per_page', 25),
            $request->input('cursor'),
        );

        $items = collect($paginator->items())->map(fn (object $row): array => [
            'invoice_id' => $row->invoice_id,
            'invoice_number' => $row->invoice_number,
            'order_number' => $row->order_number,
            'customer' => $this->customer($row->customer_snapshot),
            'issued_at' => CarbonImmutable::parse($row->issued_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'due_at' => $row->due_at === null ? null : CarbonImmutable::parse($row->due_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'total_rupiah' => (int) $row->total_rupiah,
            'balance_due_rupiah' => (int) $row->balance_due_rupiah,
            'overdue' => $row->due_at !== null && CarbonImmutable::parse($row->due_at)->lessThan($asOf->utc()),
        ])->values()->all();

        $summary = $outstandingInvoices->summary($branch, $asOf);

        return response()->json([
            'data' => $items,
            'summary' => [
                'branch' => [
                    'id' => $branch->public_id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                ],
                'as_of' => $asOfDate->toDateString(),
                'timezone' => $timezone,
                'date_basis' => 'invoice net balance as of date',
                ...$summary,
            ],
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    /**
     * @return array{name?: string|null, phone?: string|null}
     */
    private function customer(mixed $snapshot): array
    {
        $value = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;

        return [
            'name' => is_array($value) ? ($value['name'] ?? null) : null,
            'phone' => is_array($value) ? ($value['normalized_phone'] ?? null) : null,
        ];
    }
}
