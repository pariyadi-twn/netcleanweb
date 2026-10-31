<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class AssignSubscriptionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', 'max:40'],
            'plan_revision' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['trial', 'active', 'grace'])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ];
    }
}
