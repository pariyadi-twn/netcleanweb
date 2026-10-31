<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;

class StorePriceEntryRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'service_id' => [
                'required',
                'uuid',
                Rule::exists('services', 'public_id')->where('tenant_id', $tenantId),
            ],
            'tier' => ['required', 'string', Rule::enum(PriceTier::class)],
            'unit_price_rupiah' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'rules' => ['sometimes', 'array'],
        ];
    }
}
