<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    protected $model = WorkflowTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->lexify('workflow-????'),
            'revision' => 1,
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
