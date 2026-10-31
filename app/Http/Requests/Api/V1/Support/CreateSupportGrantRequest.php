<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Http\Requests\Api\V1\ApiRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class CreateSupportGrantRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'case_reference' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:2000'],
            'allowed_actions' => ['required', 'array', 'min:1'],
            'allowed_actions.*' => ['string', 'distinct'],
            'expires_at' => ['required', 'date', 'after:now'],
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
                $expires = CarbonImmutable::parse((string) $this->input('expires_at'));
                if ($expires->greaterThan(now()->addHours(24))) {
                    $validator->errors()->add('expires_at', 'A support grant cannot exceed 24 hours.');
                }
            },
        ];
    }
}
