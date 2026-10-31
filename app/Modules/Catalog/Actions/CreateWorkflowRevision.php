<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\WorkflowStep;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;

final class CreateWorkflowRevision
{
    /**
     * @param  array{code: string, name: string, steps: list<array{code: string, is_required: bool, requires_qc: bool}>}  $attributes
     */
    public function handle(int $tenantId, array $attributes): WorkflowTemplate
    {
        return DB::transaction(function () use ($tenantId, $attributes): WorkflowTemplate {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'catalog-workflow:'.$tenantId.':'.$attributes['code'],
            ]);

            $previous = WorkflowTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $attributes['code'])
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            if ($previous !== null && $previous->is_active) {
                $previous->forceFill([
                    'is_active' => false,
                    'version' => $previous->version + 1,
                ])->save();
            }

            $workflow = new WorkflowTemplate([
                'code' => $attributes['code'],
                'revision' => ($previous?->revision ?? 0) + 1,
                'name' => $attributes['name'],
                'is_active' => true,
            ]);
            $workflow->tenant_id = $tenantId;
            $workflow->save();

            foreach (array_values($attributes['steps']) as $index => $stepAttributes) {
                $step = new WorkflowStep([
                    ...$stepAttributes,
                    'workflow_template_id' => $workflow->id,
                    'sequence' => $index + 1,
                ]);
                $step->tenant_id = $tenantId;
                $step->save();
            }

            return $workflow->load('steps');
        });
    }
}
