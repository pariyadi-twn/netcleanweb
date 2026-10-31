<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class TransitionRefundRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['processing', 'succeeded', 'failed', 'cancelled'])],
            'cash_session_id' => ['nullable', 'uuid'],
            'external_ref' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
