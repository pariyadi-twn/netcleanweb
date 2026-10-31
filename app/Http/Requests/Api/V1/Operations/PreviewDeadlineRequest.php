<?php

namespace App\Http\Requests\Api\V1\Operations;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;

class PreviewDeadlineRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'branch_id' => [
                'required',
                'uuid',
                Rule::exists('branches', 'public_id')->where('tenant_id', $tenantId),
            ],
            'service_id' => [
                'required',
                'uuid',
                Rule::exists('services', 'public_id')->where('tenant_id', $tenantId),
            ],
            'tier' => ['sometimes', 'string', Rule::enum(PriceTier::class)],
            'accepted_at' => ['sometimes', 'date'],
        ];
    }
}
