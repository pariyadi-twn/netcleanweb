<?php

namespace App\Modules\Reporting\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Reporting\Enums\ImportBatchStatus;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'requested_by_membership_id',
    'type',
    'source',
    'file_name',
    'checksum',
    'raw_object_key',
    'raw_expires_at',
    'raw_purged_at',
    'dry_run',
    'status',
    'total_rows',
    'valid_rows',
    'invalid_rows',
    'duplicate_rows',
    'imported_rows',
    'completed_at',
    'error_code',
])]
class ImportBatch extends TenantModel
{
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'requested_by_membership_id' => 'integer',
            'dry_run' => 'boolean',
            'status' => ImportBatchStatus::class,
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'imported_rows' => 'integer',
            'raw_expires_at' => 'datetime',
            'raw_purged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
