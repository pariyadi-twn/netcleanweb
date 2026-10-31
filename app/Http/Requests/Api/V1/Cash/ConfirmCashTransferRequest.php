<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

class ConfirmCashTransferRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'kind' => ['required', 'string', Rule::in(['inter_register', 'bank_deposit', 'owner_funding'])],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'from_register_id' => ['nullable', 'uuid'],
            'to_register_id' => ['nullable', 'uuid'],
            'from_cash_session_id' => ['nullable', 'uuid'],
            'to_cash_session_id' => ['nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
