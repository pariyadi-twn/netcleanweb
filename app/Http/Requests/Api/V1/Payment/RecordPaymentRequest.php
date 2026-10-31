<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPaymentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::in(['cash', 'bank_manual'])],
            'cash_session_id' => ['nullable', 'uuid'],
            'cash_received_rupiah' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $method = (string) $this->input('method');
                if ($method === 'cash' && ! $this->filled('cash_session_id')) {
                    $validator->errors()->add('cash_session_id', 'A cash session is required for cash payments.');
                }
                if ($method === 'cash' && $this->filled('cash_received_rupiah') && (int) $this->input('cash_received_rupiah') < (int) $this->input('amount_rupiah')) {
                    $validator->errors()->add('cash_received_rupiah', 'Cash received cannot be less than the accepted payment.');
                }
            },
        ];
    }
}
