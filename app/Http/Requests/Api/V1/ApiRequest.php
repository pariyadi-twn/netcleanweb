<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = array_keys($this->rules());

                foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                    $validator->errors()->add($field, 'This field is not allowed.');
                }
            },
        ];
    }
}
