<?php

namespace App\Http\Requests\Api\V1\Operations;

use App\Http\Requests\Api\V1\ApiRequest;

class UpdateBranchCalendarRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weekly_hours' => ['required', 'array', 'size:7'],
            'weekly_hours.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'weekly_hours.*.is_open' => ['required', 'boolean'],
            'weekly_hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'weekly_hours.*.close_time' => ['nullable', 'date_format:H:i'],
            'holidays' => ['sometimes', 'array', 'max:366'],
            'holidays.*' => ['date_format:Y-m-d', 'distinct'],
            'cutoff_time' => ['required', 'date_format:H:i'],
            'deadline_local_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
