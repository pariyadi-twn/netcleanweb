<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class ResolveComplaintRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['resolved', 'rejected'])],
            'resolution' => ['required', 'string', 'max:2000'],
            'approved_adjustment_id' => ['nullable', 'uuid'],
        ];
    }
}
