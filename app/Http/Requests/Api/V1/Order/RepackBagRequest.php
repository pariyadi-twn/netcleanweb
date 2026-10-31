<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;

class RepackBagRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_bag_id' => ['required', 'uuid'],
            'target_bag_id' => ['sometimes', 'nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:1000'],
            'moves' => ['required', 'array', 'min:1', 'max:100'],
            'moves.*.order_line_id' => ['required', 'uuid', 'distinct'],
            'moves.*.quantity' => ['required', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
        ];
    }
}
