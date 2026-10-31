<?php

namespace App\Modules\Reporting\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Reporting\Models\ReportExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestReportExport
{
    /**
     * @param  array{branch_id: string, from: string, to: string}  $filters
     */
    public function handle(
        int $tenantId,
        int $membershipId,
        int $userId,
        string $reportType,
        array $filters,
        ?User $actor = null,
    ): ReportExport {
        return DB::transaction(function () use ($tenantId, $membershipId, $userId, $reportType, $filters): ReportExport {
            $active = ReportExport::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereIn('status', [
                        ReportExportStatus::Queued->value,
                        ReportExportStatus::Running->value,
                    ])->orWhere(function ($query): void {
                        $query->where('status', ReportExportStatus::Ready->value)
                            ->where('expires_at', '>', now());
                    });
                })
                ->count();

            if ($active >= (int) config('reports.max_active_exports', 3)) {
                throw new ApiException('EXPORT_QUOTA_EXCEEDED', 'The active export quota has been reached.', 429);
            }

            $branch = Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('public_id', $filters['branch_id'])
                ->first();
            if ($branch === null) {
                throw ValidationException::withMessages(['filters.branch_id' => ['The branch was not found.']]);
            }

            $export = new ReportExport([
                'requested_by_membership_id' => $membershipId,
                'requested_by_user_id' => $userId,
                'report_type' => $reportType,
                'filters' => $filters,
                'status' => ReportExportStatus::Queued,
                'expires_at' => now()->addHours((int) config('reports.export_expiry_hours', 24)),
            ]);
            $export->tenant_id = $tenantId;
            $export->save();

            return $export->refresh();
        });
    }
}
