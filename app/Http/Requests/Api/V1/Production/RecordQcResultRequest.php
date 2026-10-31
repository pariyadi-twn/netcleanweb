<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class RecordQcResultRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['passed', 'failed'])],
            'checklist' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
