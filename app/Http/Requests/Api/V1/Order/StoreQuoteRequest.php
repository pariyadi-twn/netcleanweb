<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuoteRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'branch_id' => ['required', 'uuid', Rule::exists('branches', 'public_id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'public_id')->where('tenant_id', $tenantId)],
            'fulfillment_method' => ['required', 'string', Rule::in(['pickup', 'delivery'])],
            'pickup_branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'public_id')->where('tenant_id', $tenantId)],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'service_discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'discount_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.service_id' => ['required', 'uuid'],
            'lines.*.measured_quantity' => ['required', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'lines.*.tier' => ['sometimes', 'string', Rule::enum(PriceTier::class)],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->input('fulfillment_method') === 'delivery' && $this->filled('pickup_branch_id')) {
                    $validator->errors()->add('pickup_branch_id', 'A delivery order cannot have a pickup branch.');
                }

                if ((int) $this->input('service_discount_percent', 0) > 10 && ! $this->filled('discount_reason')) {
                    $validator->errors()->add('discount_reason', 'A reason is required for discounts above 10 percent.');
                }
            },
        ];
    }
}
