<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class ListProductionTasksRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'uuid'],
            'status' => ['nullable', Rule::in(['queued', 'in_progress', 'done', 'blocked', 'skipped'])],
            'compact' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
