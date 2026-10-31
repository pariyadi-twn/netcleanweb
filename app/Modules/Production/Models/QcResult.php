<?php

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\BagContent;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bag_content_id',
    'production_task_id',
    'inspector_membership_id',
    'outcome',
    'checklist',
    'notes',
    'attempt_no',
])]
class QcResult extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function content(): BelongsTo
    {
        return $this->belongsTo(BagContent::class, 'bag_content_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'inspector_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'bag_content_id' => 'integer',
            'production_task_id' => 'integer',
            'inspector_membership_id' => 'integer',
            'checklist' => 'array',
            'attempt_no' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
