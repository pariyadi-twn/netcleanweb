<?php

namespace App\Modules\Reporting\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Reporting\Enums\ReportExportStatus;
use App\Modules\Uploads\Models\Attachment;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'requested_by_membership_id',
    'requested_by_user_id',
    'report_type',
    'filters',
    'status',
    'attachment_id',
    'expires_at',
    'completed_at',
    'row_count',
    'file_size_bytes',
    'error_code',
])]
class ReportExport extends TenantModel
{
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'requested_by_membership_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'filters' => 'array',
            'status' => ReportExportStatus::class,
            'attachment_id' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'row_count' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }
}
