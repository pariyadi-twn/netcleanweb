<?php

namespace App\Modules\Reporting\Models;

use App\Modules\Reporting\Enums\ImportRowStatus;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id',
    'row_number',
    'status',
    'raw_payload',
    'normalized_payload',
    'error_code',
    'error_message',
    'target_public_id',
])]
class ImportRow extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'import_batch_id' => 'integer',
            'row_number' => 'integer',
            'status' => ImportRowStatus::class,
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
