<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Http\Requests\Api\V1\ApiRequest;

class RequestRefundRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
