<?php

namespace App\Http\Requests\Api\V1\Reporting;

use App\Http\Requests\Api\V1\ApiRequest;

class ImportRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $mapping = $this->input('mapping');
        if (is_string($mapping)) {
            $decoded = json_decode($mapping, true);
            $this->merge(['mapping' => is_array($decoded) ? $decoded : $mapping]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('imports.max_file_kb', 2048), 'extensions:csv,txt'],
            'dry_run' => ['sometimes', 'boolean'],
            'mapping' => ['sometimes', 'array'],
            'mapping.*' => ['string', 'max:80'],
        ];
    }
}
