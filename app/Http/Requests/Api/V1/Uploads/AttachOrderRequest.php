<?php

namespace App\Http\Requests\Api\V1\Uploads;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class AttachOrderRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachment_id' => ['required', 'uuid'],
            'purpose' => ['required', 'string', Rule::in(['initial_condition', 'receipt', 'handover', 'complaint', 'delivery'])],
        ];
    }
}
