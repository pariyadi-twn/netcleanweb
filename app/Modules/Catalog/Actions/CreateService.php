<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Enums\TurnaroundMode;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateService
{
    /**
     * @param  array{code: string, name: string, unit: string, minimum_quantity: string, quantity_step: string, workflow_template_id: string}  $attributes
     */
    public function handle(int $tenantId, array $attributes): Service
    {
        $unit = ServiceUnit::from($attributes['unit']);
        $this->ensureValidQuantities($unit, $attributes['minimum_quantity'], $attributes['quantity_step']);
        $slaMode = TurnaroundMode::from($attributes['sla_mode'] ?? TurnaroundMode::WorkingDays->value);
        $regularTurnaround = (int) ($attributes['regular_turnaround'] ?? 2);
        $expressTurnaround = (int) ($attributes['express_turnaround'] ?? 1);
        $this->ensureValidTurnaround($slaMode, $regularTurnaround, $expressTurnaround);

        return DB::transaction(function () use ($tenantId, $attributes, $unit, $slaMode, $regularTurnaround, $expressTurnaround): Service {
            $workflow = $this->activeWorkflow($tenantId, $attributes['workflow_template_id']);

            $service = new Service([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'unit' => $unit,
                'minimum_quantity' => $attributes['minimum_quantity'],
                'quantity_step' => $attributes['quantity_step'],
                'workflow_template_id' => $workflow->id,
                'is_active' => false,
                'sla_mode' => $slaMode,
                'regular_turnaround' => $regularTurnaround,
                'express_turnaround' => $expressTurnaround,
            ]);
            $service->tenant_id = $tenantId;

            try {
                $service->save();
            } catch (QueryException $exception) {
                if ($exception->errorInfo[0] === '23505' && str_contains((string) ($exception->errorInfo[2] ?? ''), 'services_tenant_id_code_unique')) {
                    throw ValidationException::withMessages([
                        'code' => ['The code has already been taken.'],
                    ]);
                }

                throw $exception;
            }

            return $service->load('workflowTemplate.steps');
        });
    }

    private function activeWorkflow(int $tenantId, string $publicId): WorkflowTemplate
    {
        $workflow = WorkflowTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->first();

        if ($workflow === null) {
            throw ValidationException::withMessages([
                'workflow_template_id' => ['The selected workflow template must be an active revision in this tenant.'],
            ]);
        }

        return $workflow;
    }

    private function ensureValidQuantities(ServiceUnit $unit, string $minimum, string $step): void
    {
        $errors = [];

        if (! $unit->accepts($minimum)) {
            $errors['minimum_quantity'][] = 'The minimum quantity must be a whole number for this unit.';
        }

        if (! $unit->accepts($step)) {
            $errors['quantity_step'][] = 'The quantity step must be a whole number for this unit.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureValidTurnaround(TurnaroundMode $mode, int $regular, int $express): void
    {
        $maximum = $mode === TurnaroundMode::WorkingDays ? 365 : 8760;
        $errors = [];

        if ($regular < 1 || $regular > $maximum) {
            $errors['regular_turnaround'][] = 'The regular turnaround is outside the allowed range for this SLA mode.';
        }

        if ($express < 1 || $express > $maximum) {
            $errors['express_turnaround'][] = 'The express turnaround is outside the allowed range for this SLA mode.';
        }

        if ($express > $regular) {
            $errors['express_turnaround'][] = 'The express turnaround cannot exceed the regular turnaround.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
