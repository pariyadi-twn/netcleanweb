<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class IssueDeviceTokenRequest extends ApiRequest
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
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'max:120'],
            'installation_id' => ['required', 'string', 'uuid'],
            'mfa_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
