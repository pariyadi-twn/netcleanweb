<?php

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\Location;
use App\Modules\Order\Models\BagContent;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bag_content_id',
    'production_location_id',
    'step_code',
    'sequence',
    'is_required',
    'requires_qc',
    'attempt_no',
    'original_task_id',
    'status',
    'due_at',
    'started_at',
    'completed_at',
])]
class ProductionTask extends TenantModel
{
    public function content(): BelongsTo
    {
        return $this->belongsTo(BagContent::class, 'bag_content_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'production_location_id');
    }

    public function originalTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_task_id');
    }

    public function qcResults(): HasMany
    {
        return $this->hasMany(QcResult::class, 'production_task_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionTaskEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'bag_content_id' => 'integer',
            'production_location_id' => 'integer',
            'sequence' => 'integer',
            'is_required' => 'boolean',
            'requires_qc' => 'boolean',
            'attempt_no' => 'integer',
            'original_task_id' => 'integer',
            'status' => ProductionTaskStatus::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
