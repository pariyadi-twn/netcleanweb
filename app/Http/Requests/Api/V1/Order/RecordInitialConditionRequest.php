<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Validator;

class RecordInitialConditionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment_ids' => ['present', 'array', 'max:10'],
            'attachment_ids.*' => ['uuid', 'distinct'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->filled('notes') && empty($this->input('attachment_ids'))) {
                    $validator->errors()->add('notes', 'Provide notes, at least one attachment, or both.');
                }
            },
        ];
    }
}
