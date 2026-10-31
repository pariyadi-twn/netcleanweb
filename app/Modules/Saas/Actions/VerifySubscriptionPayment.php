<?php

namespace App\Modules\Saas\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Saas\Enums\SubscriptionInvoiceStatus;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Modules\Saas\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\SubscriptionPayment;
use App\Modules\Saas\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VerifySubscriptionPayment
{
    public function handle(
        SubscriptionInvoice $invoice,
        int $amountRupiah,
        string $reference,
        ?User $actor = null,
    ): SubscriptionPayment {
        return DB::transaction(function () use ($invoice, $amountRupiah, $reference, $actor): SubscriptionPayment {
            $locked = SubscriptionInvoice::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = SubscriptionPayment::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('reference', $reference)
                ->first();
            if ($existing !== null) {
                if ($existing->subscription_invoice_id !== $locked->id || $existing->amount_rupiah !== $amountRupiah) {
                    throw new ApiException('SUBSCRIPTION_REFERENCE_CONFLICT', 'The payment reference was already used for another payment.', 409);
                }

                return $existing;
            }

            if ($locked->status === SubscriptionInvoiceStatus::Paid) {
                throw new ApiException('SUBSCRIPTION_INVOICE_PAID', 'The subscription invoice is already paid.', 409);
            }
            if ($locked->status !== SubscriptionInvoiceStatus::Issued) {
                throw new ApiException('SUBSCRIPTION_INVOICE_NOT_PAYABLE', 'The subscription invoice is not payable.', 409);
            }
            if ($amountRupiah !== $locked->amount_rupiah) {
                throw ValidationException::withMessages([
                    'amount_rupiah' => ['The payment amount must match the subscription invoice.'],
                ]);
            }

            $payment = new SubscriptionPayment([
                'subscription_invoice_id' => $locked->id,
                'amount_rupiah' => $amountRupiah,
                'reference' => $reference,
                'verified_at' => now(),
                'verified_by_user_id' => $actor?->id,
            ]);
            $payment->tenant_id = $locked->tenant_id;
            $payment->save();

            $locked->forceFill([
                'status' => SubscriptionInvoiceStatus::Paid,
                'version' => $locked->version + 1,
            ])->save();

            $subscription = TenantSubscription::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $locked->period_start,
                'current_period_end' => $locked->period_end,
                'grace_until' => null,
                'version' => $subscription->version + 1,
            ])->save();

            return $payment->refresh();
        });
    }
}
