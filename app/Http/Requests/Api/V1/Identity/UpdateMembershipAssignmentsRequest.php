<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiRequest;

class UpdateMembershipAssignmentsRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_command_id' => ['required', 'string', 'uuid'],
            'role_ids' => ['present', 'array', 'max:50'],
            'role_ids.*' => ['string', 'uuid', 'distinct'],
            'branch_ids' => ['present', 'array', 'max:100'],
            'branch_ids.*' => ['string', 'uuid', 'distinct'],
            'location_ids' => ['present', 'array', 'max:100'],
            'location_ids.*' => ['string', 'uuid', 'distinct'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
