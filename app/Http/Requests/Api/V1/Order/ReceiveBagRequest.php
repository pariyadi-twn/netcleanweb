<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ReceiveBagRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'uuid', Rule::exists('locations', 'public_id')->where('tenant_id', app(TenantContext::class)->tenantId)],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
