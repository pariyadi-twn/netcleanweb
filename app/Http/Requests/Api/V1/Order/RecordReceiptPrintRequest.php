<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class RecordReceiptPrintRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['printed', 'failed'])],
            'error_code' => ['nullable', 'required_if:outcome,failed', 'string', 'max:80'],
            'tracking_token' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
