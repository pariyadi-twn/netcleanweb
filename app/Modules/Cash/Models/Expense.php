<?php

namespace App\Modules\Cash\Models;

use App\Modules\Cash\Enums\ExpenseApprovalStatus;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'cash_session_id',
    'category',
    'description',
    'amount_rupiah',
    'occurred_at',
    'approval_status',
    'requested_by_membership_id',
    'approved_by_membership_id',
    'approved_at',
])]
class Expense extends TenantModel
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'requested_by_membership_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'approved_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'branch_id' => 'integer',
            'cash_session_id' => 'integer',
            'amount_rupiah' => 'integer',
            'occurred_at' => 'datetime',
            'approval_status' => ExpenseApprovalStatus::class,
            'requested_by_membership_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}
