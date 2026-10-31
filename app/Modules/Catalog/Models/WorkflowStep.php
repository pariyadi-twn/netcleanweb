<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Identity\Models\Tenant;
use App\Support\Database\TenantModel;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_template_id', 'code', 'sequence', 'is_required', 'requires_qc'])]
#[UseFactory(WorkflowStepFactory::class)]
class WorkflowStep extends TenantModel
{
    /** @use HasFactory<WorkflowStepFactory> */
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
            'workflow_template_id' => 'integer',
            'sequence' => 'integer',
            'is_required' => 'boolean',
            'requires_qc' => 'boolean',
        ];
    }
}
