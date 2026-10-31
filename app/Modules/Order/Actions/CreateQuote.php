<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\Models\Quote;
use Illuminate\Support\Facades\DB;

final class CreateQuote
{
    public function __construct(
        private readonly NormalizeOrderInput $normalizer,
        private readonly BuildQuote $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(int $tenantId, array $input): Quote
    {
        $normalized = $this->normalizer->handle($input);
        $built = $this->builder->handle($tenantId, $normalized);

        return DB::transaction(function () use ($tenantId, $built): Quote {
            $quote = new Quote([
                'sales_branch_id' => $built['sales_branch_id'],
                'customer_id' => $built['customer_id'],
                'fulfillment_method' => $built['fulfillment_method'],
                'pickup_branch_id' => $built['pickup_branch_id'],
                'instructions' => $built['instructions'],
                'request_hash' => $built['request_hash'],
                'expires_at' => $built['expires_at'],
                'due_at' => $built['due_at'],
                'total_rupiah' => $built['total_rupiah'],
                'pricing_snapshot' => $built['pricing_snapshot'],
            ]);
            $quote->tenant_id = $tenantId;
            $quote->save();

            return $quote->refresh();
        });
    }
}
