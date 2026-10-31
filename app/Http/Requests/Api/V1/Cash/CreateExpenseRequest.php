<?php

namespace App\Http\Requests\Api\V1\Cash;

use App\Http\Requests\Api\V1\ApiRequest;

class CreateExpenseRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:2000'],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
        ];
    }
}
