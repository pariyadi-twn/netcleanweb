<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

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
            'code' => fake()->unique()->lexify('service-????'),
            'name' => fake()->words(2, true),
            'unit' => 'kg',
            'minimum_quantity' => '3.000',
            'quantity_step' => '0.100',
            'is_active' => false,
            'sla_mode' => 'working_days',
            'regular_turnaround' => 2,
            'express_turnaround' => 1,
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
