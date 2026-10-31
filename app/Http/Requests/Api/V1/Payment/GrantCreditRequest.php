<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Http\Requests\Api\V1\ApiRequest;

class GrantCreditRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit_rupiah' => ['required', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
