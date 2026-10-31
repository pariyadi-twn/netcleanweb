<?php

namespace App\Http\Requests\Api\V1\Uploads;

use App\Http\Requests\Api\V1\ApiRequest;

class TransferAttachmentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }
}
