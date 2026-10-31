<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Enums\TurnaroundMode;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateService
{
    private const MUTABLE_ATTRIBUTES = ['name', 'unit', 'minimum_quantity', 'quantity_step', 'sla_mode', 'regular_turnaround', 'express_turnaround'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Service $service, array $attributes): Service
    {
        return DB::transaction(function () use ($service, $attributes): Service {
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $changes = array_intersect_key($attributes, array_flip(self::MUTABLE_ATTRIBUTES));

            $unit = array_key_exists('unit', $changes)
                ? ServiceUnit::from((string) $changes['unit'])
                : $locked->unit;
            $minimum = (string) ($changes['minimum_quantity'] ?? $locked->minimum_quantity);
            $step = (string) ($changes['quantity_step'] ?? $locked->quantity_step);
            $slaMode = array_key_exists('sla_mode', $changes)
                ? TurnaroundMode::from((string) $changes['sla_mode'])
                : $locked->sla_mode;
            $regularTurnaround = (int) ($changes['regular_turnaround'] ?? $locked->regular_turnaround);
            $expressTurnaround = (int) ($changes['express_turnaround'] ?? $locked->express_turnaround);

            $this->ensureValidQuantities($unit, $minimum, $step);
            $this->ensureValidTurnaround($slaMode, $regularTurnaround, $expressTurnaround);

            if (array_key_exists('workflow_template_id', $attributes)) {
                $changes['workflow_template_id'] = $this->activeWorkflow(
                    $locked->tenant_id,
                    (string) $attributes['workflow_template_id'],
                )->id;
            }

            $locked->forceFill([
                ...$changes,
                'unit' => $unit,
                'sla_mode' => $slaMode,
                'regular_turnaround' => $regularTurnaround,
                'express_turnaround' => $expressTurnaround,
                'version' => $locked->version + 1,
            ])->save();

            return $locked->refresh()->load('workflowTemplate.steps');
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
