<?php

namespace App\Modules\Reporting\Jobs;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Reporting\Actions\BuildOutletReport;
use App\Modules\Reporting\Actions\CsvExport;
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Modules\Uploads\Models\Attachment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BuildReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $exportId,
        public readonly int $tenantId,
        public readonly int $userId,
    ) {
        $this->onQueue((string) config('reports.export_queue', 'exports'));
    }

    public function handle(
        TenantScope $scope,
        BuildOutletReport $buildReport,
        CsvExport $csv,
        AuditRecorder $auditor,
        OutboxWriter $outbox,
        MembershipAuthorizer $authorizer,
    ): void {
        $scope->forTenantId($this->tenantId, $this->userId, function () use ($buildReport, $csv, $auditor, $outbox, $authorizer): void {
            $export = ReportExport::query()->where('tenant_id', $this->tenantId)->whereKey($this->exportId)->firstOrFail();

            if ($export->status !== ReportExportStatus::Queued) {
                return;
            }

            $filters = $export->filters;
            $branch = Branch::query()
                ->where('tenant_id', $this->tenantId)
                ->where('public_id', $filters['branch_id'])
                ->firstOrFail();
            $user = User::query()->findOrFail($this->userId);
            $context = $authorizer->contextFor($user, Tenant::query()->findOrFail($this->tenantId));
            if ($context === null || ! $context->can(PermissionCode::ReportsExport) || ! $context->canAccessBranch($branch)) {
                $export->forceFill([
                    'status' => ReportExportStatus::Failed,
                    'error_code' => 'EXPORT_AUTHORIZATION_REVOKED',
                    'version' => $export->version + 1,
                ])->save();

                return;
            }

            $export->forceFill([
                'status' => ReportExportStatus::Running,
                'version' => $export->version + 1,
            ])->save();

            try {
                $timezone = $branch->timezone ?: 'Asia/Jakarta';
                $from = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], $timezone);
                $to = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], $timezone);
                $report = $buildReport->handle($branch, $from, $to);
                $content = $csv->build([
                    'business_date',
                    'sales_net_rupiah',
                    'invoice_original_rupiah',
                    'adjustment_rupiah',
                    'invoice_count',
                    'adjustment_count',
                    'receipts_rupiah',
                    'cash_receipts_rupiah',
                    'bank_receipts_rupiah',
                    'payment_count',
                    'refund_rupiah',
                    'refund_count',
                ], array_map(fn (array $day): array => [
                    $day['business_date'],
                    $day['sales_net_rupiah'],
                    $day['invoice_original_rupiah'],
                    $day['adjustment_rupiah'],
                    $day['invoice_count'],
                    $day['adjustment_count'],
                    $day['receipts_rupiah'],
                    $day['cash_receipts_rupiah'],
                    $day['bank_receipts_rupiah'],
                    $day['payment_count'],
                    $day['refund_rupiah'],
                    $day['refund_count'],
                ], $report['daily']));

                $key = 'exports/'.$this->tenantId.'/'.Str::uuid().'.csv';
                Storage::disk((string) config('uploads.disk'))->put($key, $content, ['visibility' => 'private']);
                $attachment = new Attachment([
                    'uploaded_by_membership_id' => $export->requested_by_membership_id,
                    'object_key' => $key,
                    'mime_type' => 'text/csv',
                    'size_bytes' => strlen($content),
                    'sha256' => hash('sha256', $content),
                    'original_name' => 'outlet-sales-'.$filters['from'].'-'.$filters['to'].'.csv',
                    'state' => AttachmentState::Ready,
                    'purpose' => 'report_export',
                ]);
                $attachment->tenant_id = $this->tenantId;
                $attachment->save();

                $export->forceFill([
                    'status' => ReportExportStatus::Ready,
                    'attachment_id' => $attachment->id,
                    'completed_at' => now(),
                    'expires_at' => now()->addHours((int) config('reports.export_expiry_hours', 24)),
                    'row_count' => count($report['daily']),
                    'file_size_bytes' => strlen($content),
                    'version' => $export->version + 1,
                ])->save();

                $auditor->record(
                    tenantId: $this->tenantId,
                    action: AuditAction::ExportCompleted,
                    subjectType: AuditRecorder::SUBJECT_REPORT_EXPORT,
                    subjectPublicId: $export->public_id,
                    changes: ['report_type' => $export->report_type, 'row_count' => $export->row_count],
                );
                $outbox->record(
                    tenantId: $this->tenantId,
                    eventType: 'export.ready',
                    aggregatePublicId: $export->public_id,
                    payload: [
                        'export_id' => $export->public_id,
                        'report_type' => $export->report_type,
                        'row_count' => $export->row_count,
                    ],
                );
            } catch (Throwable $exception) {
                $export->forceFill([
                    'status' => ReportExportStatus::Failed,
                    'error_code' => 'EXPORT_GENERATION_FAILED',
                    'version' => $export->version + 1,
                ])->save();

                throw $exception;
            }
        });
    }
}
