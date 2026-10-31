<?php

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Validator;

class StoreOrderRevisionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.order_line_id' => ['required', 'uuid', 'distinct'],
            'lines.*.measured_quantity' => ['sometimes', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'lines.*.service_id' => ['sometimes', 'uuid'],
            'lines.*.reprice' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                foreach ((array) $this->input('lines', []) as $index => $line) {
                    if (! isset($line['measured_quantity']) && ! isset($line['service_id']) && ! ($line['reprice'] ?? false)) {
                        $validator->errors()->add('lines.'.$index, 'A revision line must change quantity, service, or reprice.');
                    }
                }
            },
        ];
    }
}
