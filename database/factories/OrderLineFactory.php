<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail($attributes['order_id'])->tenant_id,
            'service_id' => function (array $attributes): int {
                $workflow = WorkflowTemplate::factory()->create(['tenant_id' => $attributes['tenant_id']]);

                return Service::factory()->forWorkflow($workflow)->create()->id;
            },
            'unit' => 'kg',
            'measured_quantity' => '1.000',
            'billable_quantity' => '1.000',
            'unit_price_rupiah' => 8000,
            'line_total_rupiah' => 8000,
            'service_snapshot' => [],
            'status' => 'active',
        ];
    }
}
