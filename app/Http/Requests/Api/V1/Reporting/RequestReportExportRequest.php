<?php

namespace App\Http\Requests\Api\V1\Reporting;

use App\Http\Requests\Api\V1\ApiRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequestReportExportRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'report_type' => ['required', 'string', Rule::in(['outlet_sales'])],
            'filters' => ['required', 'array:branch_id,from,to'],
            'filters.branch_id' => ['required', 'uuid'],
            'filters.from' => ['required', 'date_format:Y-m-d'],
            'filters.to' => ['required', 'date_format:Y-m-d', 'after_or_equal:filters.from'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('filters.from'));
                $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('filters.to'));
                if ($from === false || $to === false || $to->lessThan($from)) {
                    $validator->errors()->add('filters.to', 'The export end date must not precede the start date.');

                    return;
                }
                if ($from->diffInDays($to) > 92) {
                    $validator->errors()->add('filters.to', 'The export period must not exceed 92 days.');
                }
            },
        ];
    }
}
