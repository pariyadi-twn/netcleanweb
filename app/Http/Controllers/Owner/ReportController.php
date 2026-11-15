<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Actions\BuildOutletReport;
use App\Modules\Reporting\Actions\OutstandingInvoices;
use App\Modules\Reporting\Actions\RequestReportExport;
use App\Modules\Reporting\Actions\RunImport;
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Reporting\Jobs\BuildReportExport;
use App\Modules\Reporting\Models\ImportBatch;
use App\Modules\Reporting\Models\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::ReportsView), 403);

        return view('owner.reports.index', [
            'user' => $request->user(),
            'branches' => $this->branches($context),
            'exports' => ReportExport::query()
                ->where('tenant_id', $context->tenantId)
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function imports(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::ImportsManage), 403);

        return view('owner.reports.imports', [
            'user' => $request->user(),
            'batches' => ImportBatch::query()->where('tenant_id', $context->tenantId)->latest('created_at')->limit(15)->get(),
        ]);
    }

    public function storeImport(Request $request, TenantContext $context, RunImport $import): RedirectResponse
    {
        abort_unless($context->can(PermissionCode::ImportsManage), 403);
        $validated = $request->validate([
            'type' => ['required', 'in:customers,catalog'],
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        Gate::authorize('create', ImportBatch::class);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $batch = $import->handle($context->tenantId, $context->membershipId, $validated['type'], $file, $request->boolean('dry_run'), [], $request->user());

        return redirect()->route('reports.imports')->with('status', 'Impor selesai: '.$batch->imported_rows.' baris, '.$batch->invalid_rows.' gagal.');
    }

    public function outlet(Request $request, TenantContext $context, BuildOutletReport $build): View
    {
        [$branch, $from, $to] = $this->period($request, $context);

        return view('owner.reports.outlet', [
            'user' => $request->user(),
            'branches' => $this->branches($context),
            'branch' => $branch,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'report' => $build->handle($branch, $from, $to),
        ]);
    }

    public function receivables(Request $request, TenantContext $context, OutstandingInvoices $outstanding): View
    {
        $branch = $this->branch($request, $context);
        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $asOfDate = $request->filled('as_of')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('as_of')->toString(), $timezone)
            : CarbonImmutable::now($timezone)->startOfDay();
        $asOf = $asOfDate->endOfDay();

        return view('owner.reports.receivables', [
            'user' => $request->user(),
            'branches' => $this->branches($context),
            'branch' => $branch,
            'asOf' => $asOfDate->toDateString(),
            'rows' => $outstanding->paginate($branch, $asOf, 25, $request->input('cursor')),
            'summary' => $outstanding->summary($branch, $asOf),
        ]);
    }

    public function storeExport(Request $request, TenantContext $context, RequestReportExport $requestExport): RedirectResponse
    {
        [$branch, $from, $to] = $this->period($request, $context);
        Gate::authorize('create', ReportExport::class);

        $export = $requestExport->handle(
            $context->tenantId,
            $context->membershipId,
            $request->user()->id,
            'outlet_sales',
            ['branch_id' => $branch->public_id, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            $request->user(),
        );

        BuildReportExport::dispatch($export->id, $context->tenantId, $request->user()->id);

        return redirect()->route('reports.exports.show', $export)->with('status', 'Ekspor laporan sedang diproses.');
    }

    public function showExport(string $export, TenantContext $context): View
    {
        $model = $this->findExport($export, $context);
        Gate::authorize('view', $model);
        $this->expire($model);

        return view('owner.reports.export', ['user' => auth()->user(), 'export' => $model]);
    }

    public function downloadExport(string $export, TenantContext $context): StreamedResponse
    {
        $model = $this->findExport($export, $context);
        Gate::authorize('download', $model);
        $this->expire($model);

        if ($model->status !== ReportExportStatus::Ready || $model->attachment === null) {
            abort(410, 'Ekspor belum tersedia.');
        }

        $disk = Storage::disk((string) config('uploads.disk'));
        abort_unless($disk->exists($model->attachment->object_key), 410);

        return $disk->download($model->attachment->object_key, $model->attachment->original_name ?: 'laporan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function period(Request $request, TenantContext $context): array
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $branch = $this->branch($request, $context);
        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['from'], $timezone);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $validated['to'], $timezone);

        if ($from->diffInDays($to) > 92) {
            abort(422, 'Rentang laporan maksimal 92 hari.');
        }

        return [$branch, $from, $to];
    }

    private function branch(Request $request, TenantContext $context): Branch
    {
        $branch = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $request->string('branch_id')->toString())
            ->firstOrFail();
        Gate::authorize('viewReports', $branch);

        return $branch;
    }

    private function branches(TenantContext $context): Collection
    {
        return Branch::query()->where('tenant_id', $context->tenantId)->whereIn('id', $context->branchIds)->orderBy('name')->get();
    }

    private function findExport(string $publicId, TenantContext $context): ReportExport
    {
        return ReportExport::query()->where('tenant_id', $context->tenantId)->where('public_id', $publicId)->with('attachment')->firstOrFail();
    }

    private function expire(ReportExport $export): void
    {
        if ($export->status === ReportExportStatus::Ready && $export->expires_at?->isPast()) {
            $export->forceFill(['status' => ReportExportStatus::Expired, 'version' => $export->version + 1])->save();
        }
    }
}
