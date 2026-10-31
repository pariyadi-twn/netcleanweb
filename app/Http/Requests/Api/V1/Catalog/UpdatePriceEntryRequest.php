<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;

class UpdatePriceEntryRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_to' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
