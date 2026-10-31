<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\ApiRequest;

class VerifySubscriptionPaymentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:150'],
        ];
    }
}
