<?php

namespace App\Http\Requests\Api\V1\Uploads;

use App\Http\Requests\Api\V1\ApiRequest;

class InitAttachmentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'original_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:5242880'],
            'sha256' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }
}
