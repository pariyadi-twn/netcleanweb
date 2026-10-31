<?php

namespace App\Http\Requests\Api\V1\Production;

use App\Http\Requests\Api\V1\ApiRequest;

class PlanProductionTasksRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'uuid'],
        ];
    }
}
