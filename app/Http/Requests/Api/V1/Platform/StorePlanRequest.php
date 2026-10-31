<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\ApiRequest;

class StorePlanRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'revision' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'base_price_rupiah' => ['required', 'integer', 'min:0'],
            'features' => ['required', 'array'],
            'features.limits' => ['required', 'array'],
            'features.limits.*' => ['integer', 'min:0'],
            'features.features' => ['required', 'array'],
            'features.features.*' => ['boolean'],
        ];
    }
}
