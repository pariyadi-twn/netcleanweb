<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class CreateComplaintRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['rewash', 'missing', 'damaged', 'other'])],
            'order_line_id' => ['nullable', 'uuid'],
            'description' => ['required', 'string', 'max:2000'],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
