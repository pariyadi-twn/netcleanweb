<?php

namespace App\Modules\Lifecycle\Models;

use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['data_request_id', 'subject_type', 'subject_public_id', 'reason', 'applied_at'])]
class DataDeletionTombstone extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'data_request_id' => 'integer',
            'applied_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
