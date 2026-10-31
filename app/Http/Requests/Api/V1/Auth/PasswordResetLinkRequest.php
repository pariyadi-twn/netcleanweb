<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class PasswordResetLinkRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ];
    }
}
