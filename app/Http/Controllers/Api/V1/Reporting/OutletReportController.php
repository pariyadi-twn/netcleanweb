<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\OutletReportRequest;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Actions\BuildOutletReport;
use App\Support\Http\CorrelationId;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OutletReportController extends Controller
{
    public function __invoke(OutletReportRequest $request, BuildOutletReport $build): JsonResponse
    {
        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id'))
            ->firstOrFail();

        Gate::authorize('viewReports', $branch);

        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('from')->toString(), $timezone);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('to')->toString(), $timezone);
        $report = $build->handle($branch, $from, $to);

        return response()->json([
            'data' => $report,
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'watermark' => $report['watermark'],
            ],
        ]);
    }
}
