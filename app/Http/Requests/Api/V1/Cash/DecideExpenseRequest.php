<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class DecideExpenseRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'cash_session_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
