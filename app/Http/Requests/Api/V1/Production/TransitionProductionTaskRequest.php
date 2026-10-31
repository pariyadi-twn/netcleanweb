<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class TransitionProductionTaskRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'to_status' => ['required', Rule::in(['in_progress', 'done', 'blocked', 'skipped', 'queued'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
