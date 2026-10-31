<?php

namespace Database\Factories;

use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerConsent>
 */
class CustomerConsentFactory extends Factory
{
    protected $model = CustomerConsent::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'tenant_id' => fn (array $attributes): int => Customer::query()->findOrFail($attributes['customer_id'])->tenant_id,
            'purpose' => ConsentPurpose::ServiceContact,
            'channel' => ConsentChannel::Whatsapp,
            'decision' => ConsentDecision::Granted,
            'source' => 'test',
            'occurred_at' => now(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
        ]);
    }
}
