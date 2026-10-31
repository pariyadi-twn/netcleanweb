<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\RequestReportExportRequest;
use App\Http\Resources\Api\V1\ReportExportResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Actions\RequestReportExport;
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Reporting\Jobs\BuildReportExport;
use App\Modules\Reporting\Models\ReportExport;
use App\Support\Http\CorrelationId;
use App\Support\Idempotency\CommandReceiptService;
use App\Support\Idempotency\CommandResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function store(
        RequestReportExportRequest $request,
        CommandReceiptService $receipts,
        RequestReportExport $requestExport,
    ): JsonResponse {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $clientCommandId = $request->string('client_command_id')->toString();
        if (! Str::isUuid($idempotencyKey) || $idempotencyKey !== $clientCommandId) {
            throw ValidationException::withMessages([
                'client_command_id' => ['The Idempotency-Key header must be a valid UUID matching client_command_id.'],
            ]);
        }

        $context = app(TenantContext::class);
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->input('filters.branch_id'))
            ->firstOrFail();
        Gate::authorize('viewReports', $branch);
        Gate::authorize('create', ReportExport::class);

        $export = null;
        $result = $receipts->run(
            tenantId: $context->tenantId,
            clientCommandId: $clientCommandId,
            commandType: 'report.export',
            payload: $request->validated(),
            actorMembershipId: $context->membershipId,
            authorize: fn (): bool => Gate::forUser($request->user())->allows('create', ReportExport::class),
            execute: function () use ($request, $requestExport, $context, &$export): CommandResult {
                $export = $requestExport->handle(
                    $context->tenantId,
                    $context->membershipId,
                    $request->user()->id,
                    $request->string('report_type')->toString(),
                    $request->input('filters'),
                    $request->user(),
                );

                return new CommandResult(
                    resourceType: 'report_export',
                    resourcePublicId: $export->public_id,
                    data: (new ReportExportResource($export))->resolve(),
                );
            },
            resolve: function ($receipt) use ($context): CommandResult {
                $export = ReportExport::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('public_id', $receipt->result_public_id)
                    ->firstOrFail();

                return new CommandResult(
                    resourceType: 'report_export',
                    resourcePublicId: $export->public_id,
                    data: (new ReportExportResource($export))->resolve(),
                );
            },
        );

        if ($export !== null) {
            $userId = $request->user()->id;
            DB::afterCommit(fn () => BuildReportExport::dispatch($export->id, $context->tenantId, $userId));
        }

        return response()->json([
            'data' => $result->data,
            'meta' => ['idempotent_replay' => $result->replay, 'correlation_id' => CorrelationId::current()],
        ], $result->replay ? 200 : 202);
    }

    public function show(string $export): JsonResponse
    {
        $model = $this->find($export);
        Gate::authorize('view', $model);
        $this->expireIfNeeded($model);

        return response()->json([
            'data' => (new ReportExportResource($model))->resolve(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function download(string $export): StreamedResponse
    {
        $model = $this->find($export);
        Gate::authorize('download', $model);
        $this->expireIfNeeded($model);

        if ($model->status !== ReportExportStatus::Ready || $model->attachment === null) {
            abort(410, 'The export is not available for download.');
        }

        $disk = Storage::disk((string) config('uploads.disk'));
        if (! $disk->exists($model->attachment->object_key)) {
            abort(410, 'The export file is no longer available.');
        }

        return $disk->download(
            $model->attachment->object_key,
            $model->attachment->original_name ?: 'report-export.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store'],
        );
    }

    private function find(string $publicId): ReportExport
    {
        return ReportExport::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function expireIfNeeded(ReportExport $export): void
    {
        if ($export->status === ReportExportStatus::Ready && $export->expires_at !== null && $export->expires_at->isPast()) {
            $export->forceFill([
                'status' => ReportExportStatus::Expired,
                'version' => $export->version + 1,
            ])->save();
        }
    }
}
