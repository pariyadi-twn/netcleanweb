<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceEntry>
 */
class PriceEntryFactory extends Factory
{
    protected $model = PriceEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_book_id' => PriceBook::factory(),
            'tenant_id' => fn (array $attributes): int => PriceBook::query()->findOrFail($attributes['price_book_id'])->tenant_id,
            'service_id' => function (array $attributes): int {
                $tenantId = PriceBook::query()->findOrFail($attributes['price_book_id'])->tenant_id;
                $workflow = WorkflowTemplate::factory()->create(['tenant_id' => $tenantId]);

                return Service::factory()->forWorkflow($workflow)->create()->id;
            },
            'tier' => 'regular',
            'unit_price_rupiah' => 8000,
            'effective_from' => now()->startOfDay(),
            'effective_until' => null,
            'rules' => [],
        ];
    }

    public function forPriceBook(PriceBook $book, Service $service): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $book->tenant_id,
            'price_book_id' => $book->id,
            'service_id' => $service->id,
        ]);
    }
}
