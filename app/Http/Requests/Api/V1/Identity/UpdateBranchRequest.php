<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiRequest;

class UpdateBranchRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'address' => ['sometimes', 'required', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
