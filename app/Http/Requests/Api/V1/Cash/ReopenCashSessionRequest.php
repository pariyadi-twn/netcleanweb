<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;

class ReopenCashSessionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
