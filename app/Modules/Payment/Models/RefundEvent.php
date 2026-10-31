<?php

namespace App\Modules\Payment\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Payment\Enums\RefundStatus;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_id', 'from_status', 'to_status', 'actor_membership_id', 'provider_ref', 'reason'])]
class RefundEvent extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'refund_id' => 'integer',
            'from_status' => RefundStatus::class,
            'to_status' => RefundStatus::class,
            'actor_membership_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
