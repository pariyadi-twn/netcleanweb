<?php

namespace App\Modules\Operations\Models;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Enums\CommandReceiptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'client_command_id',
    'command_type',
    'request_hash',
    'actor_membership_id',
    'status',
    'result_resource_type',
    'result_public_id',
    'completed_at',
    'error_code',
])]
class CommandReceipt extends Model
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actorMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'actor_membership_id' => 'integer',
            'status' => CommandReceiptStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
