<?php

namespace App\Modules\Order\Actions;

use App\Exceptions\ApiException;
use App\Modules\Order\Enums\DiscountApprovalStatus;
use App\Modules\Order\Models\DiscountApproval;
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
    public function handle(
        int $tenantId,
        array $input,
        int $actorMembershipId,
        bool $canStandardDiscount,
        bool $canOverrideDiscount,
    ): Quote {
        $normalized = $this->normalizer->handle($input);
        $percent = $normalized['service_discount_percent'];

        if ($percent > 0 && ! $canStandardDiscount && ! $canOverrideDiscount) {
            throw new ApiException('DISCOUNT_NOT_ALLOWED', 'This actor is not allowed to apply a discount.', 403);
        }

        $built = $this->builder->handle($tenantId, $normalized);

        return DB::transaction(function () use ($tenantId, $built, $percent, $actorMembershipId, $normalized): Quote {
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

            if ($percent > 0) {
                $approval = new DiscountApproval([
                    'quote_id' => $quote->id,
                    'percent' => $percent,
                    'requested_by_membership_id' => $actorMembershipId,
                    'approved_by_membership_id' => $percent > 10 ? null : $actorMembershipId,
                    'status' => $percent > 10 ? DiscountApprovalStatus::Pending : DiscountApprovalStatus::Approved,
                    'reason' => $normalized['discount_reason'],
                    'decision_reason' => $percent > 10 ? null : 'Auto-approved within standard limit',
                    'decided_at' => $percent > 10 ? null : now(),
                ]);
                $approval->tenant_id = $tenantId;
                $approval->save();
            }

            return $quote->refresh()->load('discountApproval');
        });
    }
}
