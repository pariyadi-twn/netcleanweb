<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;

class CancelOrderRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
