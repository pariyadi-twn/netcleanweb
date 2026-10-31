<?php

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['production_task_id', 'actor_membership_id', 'from_status', 'to_status', 'reason'])]
class ProductionTaskEvent extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'production_task_id' => 'integer',
            'actor_membership_id' => 'integer',
            'from_status' => ProductionTaskStatus::class,
            'to_status' => ProductionTaskStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
