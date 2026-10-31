<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Catalog\Enums\ServiceUnit;
use App\Modules\Catalog\Enums\TurnaroundMode;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId;

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('services', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', Rule::enum(ServiceUnit::class)],
            'minimum_quantity' => ['required', 'string', 'numeric', 'min:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'quantity_step' => ['required', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'workflow_template_id' => ['required', 'uuid'],
            'sla_mode' => ['sometimes', 'string', Rule::enum(TurnaroundMode::class)],
            'regular_turnaround' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'express_turnaround' => ['sometimes', 'integer', 'min:1', 'max:8760'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['unit', 'minimum_quantity', 'quantity_step'])) {
                    return;
                }

                $unit = ServiceUnit::from((string) $this->input('unit'));

                foreach (['minimum_quantity', 'quantity_step'] as $field) {
                    if (! $unit->accepts((string) $this->input($field))) {
                        $validator->errors()->add($field, 'The '.$field.' must be a whole number for this unit.');
                    }
                }
            },
        ];
    }
}
