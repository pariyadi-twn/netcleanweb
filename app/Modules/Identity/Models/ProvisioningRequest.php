<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\ProvisioningStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'client_command_id',
    'request_hash',
    'status',
    'tenant_id',
    'tenant_public_id',
    'branch_id',
    'branch_public_id',
    'membership_id',
    'membership_public_id',
    'error_code',
    'completed_at',
])]
class ProvisioningRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'membership_id' => 'integer',
            'status' => ProvisioningStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
