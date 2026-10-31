<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use Illuminate\Support\Facades\DB;

final class RecordCustomerConsent
{
    /**
     * @param  array{purpose: string, channel: string, decision: string, source: string, occurred_at?: string|null}  $attributes
     */
    public function handle(Customer $customer, array $attributes): CustomerConsent
    {
        return DB::transaction(function () use ($customer, $attributes): CustomerConsent {
            $consent = new CustomerConsent([
                'customer_id' => $customer->id,
                'purpose' => $attributes['purpose'],
                'channel' => $attributes['channel'],
                'decision' => $attributes['decision'],
                'source' => $attributes['source'],
                'occurred_at' => $attributes['occurred_at'] ?? now(),
            ]);
            $consent->tenant_id = $customer->tenant_id;
            $consent->save();

            return $consent->refresh();
        });
    }
}
