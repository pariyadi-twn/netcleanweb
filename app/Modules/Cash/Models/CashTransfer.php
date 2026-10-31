<?php

namespace App\Modules\Cash\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_register_id',
    'to_register_id',
    'from_cash_session_id',
    'to_cash_session_id',
    'amount_rupiah',
    'kind',
    'status',
    'reference',
    'reason',
    'requested_by_membership_id',
    'confirmed_by_membership_id',
    'confirmed_at',
])]
class CashTransfer extends TenantModel
{
    public function fromRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'from_register_id');
    }

    public function toRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'to_register_id');
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'from_cash_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'to_cash_session_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'confirmed_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'from_register_id' => 'integer',
            'to_register_id' => 'integer',
            'from_cash_session_id' => 'integer',
            'to_cash_session_id' => 'integer',
            'amount_rupiah' => 'integer',
            'requested_by_membership_id' => 'integer',
            'confirmed_by_membership_id' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }
}
