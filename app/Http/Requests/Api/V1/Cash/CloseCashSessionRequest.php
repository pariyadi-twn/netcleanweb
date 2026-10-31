<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;

class CloseCashSessionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_closing_rupiah' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
