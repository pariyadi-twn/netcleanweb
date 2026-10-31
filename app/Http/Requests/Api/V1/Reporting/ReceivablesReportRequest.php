<?php

namespace App\Http\Requests\Api\V1\Reporting;

use App\Http\Requests\Api\V1\ApiRequest;

class ReceivablesReportRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
