<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Service;

final class CaptureServiceSnapshot
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     unit: string,
     *     minimum_quantity: string,
     *     quantity_step: string,
     *     sla_mode: string,
     *     regular_turnaround: int,
     *     express_turnaround: int,
     *     version: int,
     *     workflow: array{
     *         id: string,
     *         code: string,
     *         revision: int,
     *         name: string,
     *         steps: list<array{code: string, sequence: int, is_required: bool, requires_qc: bool}>
     *     }
     * }
     */
    public function handle(Service $service): array
    {
        $service->loadMissing('workflowTemplate.steps');
        $workflow = $service->workflowTemplate;

        return [
            'id' => $service->public_id,
            'code' => $service->code,
            'name' => $service->name,
            'unit' => $service->unit->value,
            'minimum_quantity' => $service->minimum_quantity,
            'quantity_step' => $service->quantity_step,
            'sla_mode' => $service->sla_mode->value,
            'regular_turnaround' => $service->regular_turnaround,
            'express_turnaround' => $service->express_turnaround,
            'version' => $service->version,
            'workflow' => [
                'id' => $workflow->public_id,
                'code' => $workflow->code,
                'revision' => $workflow->revision,
                'name' => $workflow->name,
                'steps' => $workflow->steps->map(fn ($step): array => [
                    'code' => $step->code,
                    'sequence' => $step->sequence,
                    'is_required' => $step->is_required,
                    'requires_qc' => $step->requires_qc,
                ])->values()->all(),
            ],
        ];
    }
}
