<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class DecideRefundRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
