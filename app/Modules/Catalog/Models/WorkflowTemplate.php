<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Identity\Models\Tenant;
use App\Support\Database\TenantModel;
use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'revision', 'name', 'is_active'])]
#[UseFactory(WorkflowTemplateFactory::class)]
class WorkflowTemplate extends TenantModel
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sequence');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'revision' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
