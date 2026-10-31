<?php

namespace App\Modules\Payment\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Invoice;
use App\Support\Database\TenantModel;
use Database\Factories\CreditApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'approved_by_membership_id', 'limit_rupiah', 'expires_at', 'reason', 'status'])]
#[UseFactory(CreditApprovalFactory::class)]
class CreditApproval extends TenantModel
{
    /** @use HasFactory<CreditApprovalFactory> */
    use HasFactory;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
            'invoice_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'limit_rupiah' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
