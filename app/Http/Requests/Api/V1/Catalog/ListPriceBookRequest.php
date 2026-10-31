<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;

class ListPriceBookRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
            'branch_id' => [
                'sometimes',
                'uuid',
                Rule::exists('branches', 'public_id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
