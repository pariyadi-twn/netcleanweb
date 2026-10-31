<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\AuditAction;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'actor_user_id',
    'actor_membership_id',
    'action',
    'subject_type',
    'subject_public_id',
    'correlation_id',
    'redacted_changes',
    'reason',
])]
class AuditLog extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
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
            'actor_user_id' => 'integer',
            'actor_membership_id' => 'integer',
            'action' => AuditAction::class,
            'redacted_changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
