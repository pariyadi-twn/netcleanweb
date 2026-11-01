<?php

namespace App\Http\Requests\Api\V1\Logistics;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class CreateHandoverRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bag_ids' => ['required', 'array', 'min:1', 'max:100'],
            'bag_ids.*' => ['uuid', 'distinct'],
            'location_id' => ['required', 'uuid'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'verification_method' => ['required', Rule::in(['receipt', 'otp', 'verified_staff_exception'])],
            'credit_approval_id' => ['nullable', 'uuid'],
        ];
    }
}
