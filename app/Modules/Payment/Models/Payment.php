<?php

namespace App\Modules\Payment\Models;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\HasPublicUuid;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_attempt_id',
    'customer_id',
    'received_branch_id',
    'cash_session_id',
    'method',
    'amount_rupiah',
    'currency',
    'cash_received_rupiah',
    'change_rupiah',
    'unallocated_rupiah',
    'received_at',
    'received_by_membership_id',
])]
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivedBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'received_branch_id');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'received_by_membership_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'payment_attempt_id' => 'integer',
            'customer_id' => 'integer',
            'received_branch_id' => 'integer',
            'cash_session_id' => 'integer',
            'amount_rupiah' => 'integer',
            'cash_received_rupiah' => 'integer',
            'change_rupiah' => 'integer',
            'unallocated_rupiah' => 'integer',
            'received_at' => 'datetime',
            'received_by_membership_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
