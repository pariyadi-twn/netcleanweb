<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rules\Password;

class PasswordResetRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'password_confirmation' => ['required', 'string', 'max:1024'],
        ];
    }
}
