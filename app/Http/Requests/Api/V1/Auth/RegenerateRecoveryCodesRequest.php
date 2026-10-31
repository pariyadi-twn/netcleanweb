<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class RegenerateRecoveryCodesRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:1024'],
        ];
    }
}
