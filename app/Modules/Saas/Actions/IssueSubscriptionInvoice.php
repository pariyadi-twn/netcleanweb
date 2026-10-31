<?php

namespace App\Modules\Saas\Actions;

use App\Models\User;
use App\Modules\Saas\Enums\SubscriptionInvoiceStatus;
use App\Modules\Saas\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\TenantSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueSubscriptionInvoice
{
    public function handle(
        TenantSubscription $subscription,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        string $kind = 'subscription',
        ?User $actor = null,
    ): SubscriptionInvoice {
        return DB::transaction(function () use ($subscription, $periodStart, $periodEnd, $kind): SubscriptionInvoice {
            $locked = TenantSubscription::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = SubscriptionInvoice::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('subscription_id', $locked->id)
                ->where('kind', $kind)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();
            if ($existing !== null) {
                return $existing->load('subscription.plan');
            }

            $invoice = new SubscriptionInvoice([
                'subscription_id' => $locked->id,
                'platform_invoice_number' => 'SINV-'.now()->format('Ym').'-'.Str::upper(Str::random(10)),
                'kind' => $kind,
                'amount_rupiah' => $locked->plan->base_price_rupiah,
                'status' => SubscriptionInvoiceStatus::Issued,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
            ]);
            $invoice->tenant_id = $locked->tenant_id;
            $invoice->save();

            return $invoice->refresh()->load('subscription.plan');
        });
    }
}
