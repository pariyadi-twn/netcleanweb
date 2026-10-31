<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use Illuminate\Validation\Rule;

class StoreCustomerConsentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::enum(ConsentPurpose::class)],
            'channel' => ['required', 'string', Rule::enum(ConsentChannel::class)],
            'decision' => ['required', 'string', Rule::enum(ConsentDecision::class)],
            'source' => ['required', 'string', 'max:80'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
