<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiRequest;

class ProvisionTenantRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'string', 'uuid'],
            'organization_name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'timezone' => ['required', 'string', 'timezone'],
            'branch_name' => ['required', 'string', 'max:150'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'branch_timezone' => ['nullable', 'string', 'timezone'],
            'branch_address' => ['required', 'string', 'max:2000'],
            'branch_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
