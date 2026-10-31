<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;

class StoreWorkflowRevisionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:120'],
            'steps' => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'distinct:strict'],
            'steps.*.is_required' => ['required', 'boolean'],
            'steps.*.requires_qc' => ['required', 'boolean'],
        ];
    }
}
