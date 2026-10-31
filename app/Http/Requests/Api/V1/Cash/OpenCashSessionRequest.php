<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;

class OpenCashSessionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_rupiah' => ['required', 'integer', 'min:0'],
        ];
    }
}
