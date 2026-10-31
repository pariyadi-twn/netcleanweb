<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiRequest;

class StoreCustomerRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'addresses' => ['sometimes', 'array', 'max:10'],
            'addresses.*.label' => ['required', 'string', 'max:60'],
            'addresses.*.address' => ['required', 'string', 'max:2000'],
            'addresses.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'addresses.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'addresses.*.is_default' => ['sometimes', 'boolean'],
        ];
    }
}
