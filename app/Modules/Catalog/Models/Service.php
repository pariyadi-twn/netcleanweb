<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Enums\TurnaroundMode;
use App\Modules\Identity\Models\Tenant;
use App\Support\Database\TenantModel;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'unit', 'minimum_quantity', 'quantity_step', 'workflow_template_id', 'is_active', 'sla_mode', 'regular_turnaround', 'express_turnaround'])]
#[UseFactory(ServiceFactory::class)]
class Service extends TenantModel
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'unit' => ServiceUnit::class,
            'minimum_quantity' => 'decimal:3',
            'quantity_step' => 'decimal:3',
            'workflow_template_id' => 'integer',
            'is_active' => 'boolean',
            'sla_mode' => TurnaroundMode::class,
            'regular_turnaround' => 'integer',
            'express_turnaround' => 'integer',
        ];
    }
}
