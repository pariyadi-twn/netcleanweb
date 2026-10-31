<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;

class StorePriceBookRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => [
                'nullable',
                'uuid',
                Rule::exists('branches', 'public_id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
