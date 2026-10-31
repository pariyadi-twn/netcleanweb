<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class NewPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}
