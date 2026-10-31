<?php

namespace App\Http\Requests\Api\V1\Reporting;

use App\Http\Requests\Api\V1\ApiRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class OutletReportRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
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
                $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('from'));
                $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('to'));
                if ($from === false || $to === false || $to->lessThan($from)) {
                    $validator->errors()->add('to', 'The report end date must not precede the start date.');

                    return;
                }
                if ($from->diffInDays($to) > 92) {
                    $validator->errors()->add('to', 'The report period must not exceed 92 days.');
                }
            },
        ];
    }
}
