<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiRequest;

class ListCustomerOrdersRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ];
    }
}
