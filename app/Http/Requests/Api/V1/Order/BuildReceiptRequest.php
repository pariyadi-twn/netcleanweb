<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;

class BuildReceiptRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tracking_token' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
