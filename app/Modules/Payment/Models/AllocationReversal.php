<?php

namespace App\Modules\Payment\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_allocation_id', 'amount_rupiah', 'reason', 'approved_by_membership_id'])]
class AllocationReversal extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'payment_allocation_id' => 'integer',
            'amount_rupiah' => 'integer',
            'approved_by_membership_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
