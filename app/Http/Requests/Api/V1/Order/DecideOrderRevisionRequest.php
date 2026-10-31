<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class DecideOrderRevisionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
