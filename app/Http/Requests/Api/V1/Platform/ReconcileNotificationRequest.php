<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\ApiRequest;

class ReconcileNotificationRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:sent,failed'],
            'provider_reference' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
