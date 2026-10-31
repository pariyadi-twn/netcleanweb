<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Enums\TurnaroundMode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'unit' => ['sometimes', 'required', 'string', Rule::enum(ServiceUnit::class)],
            'minimum_quantity' => ['sometimes', 'required', 'string', 'numeric', 'min:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'quantity_step' => ['sometimes', 'required', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'workflow_template_id' => ['sometimes', 'required', 'uuid'],
            'sla_mode' => ['sometimes', 'required', 'string', Rule::enum(TurnaroundMode::class)],
            'regular_turnaround' => ['sometimes', 'required', 'integer', 'min:1', 'max:8760'],
            'express_turnaround' => ['sometimes', 'required', 'integer', 'min:1', 'max:8760'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'unit', 'minimum_quantity', 'quantity_step', 'workflow_template_id', 'sla_mode', 'regular_turnaround', 'express_turnaround'])) {
                    $validator->errors()->add('request', 'At least one service field must be provided.');
                }
            },
        ];
    }
}
