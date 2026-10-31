<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\ApiRequest;

class DecideDataRequestRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verification_method' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
