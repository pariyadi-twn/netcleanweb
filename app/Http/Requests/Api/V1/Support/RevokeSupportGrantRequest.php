<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Http\Requests\Api\V1\ApiRequest;

class RevokeSupportGrantRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
