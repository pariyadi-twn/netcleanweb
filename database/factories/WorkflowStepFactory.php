<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\WorkflowStep;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_template_id' => WorkflowTemplate::factory(),
            'tenant_id' => fn (array $attributes): int => WorkflowTemplate::query()->findOrFail($attributes['workflow_template_id'])->tenant_id,
            'code' => fake()->unique()->lexify('step-????'),
            'sequence' => 1,
            'is_required' => true,
            'requires_qc' => false,
        ];
    }

    public function forWorkflow(WorkflowTemplate $workflow): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $workflow->tenant_id,
            'workflow_template_id' => $workflow->id,
        ]);
    }
}
