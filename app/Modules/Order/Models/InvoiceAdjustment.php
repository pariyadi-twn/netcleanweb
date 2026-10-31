<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\InvoiceAdjustmentKind;
use App\Support\Database\HasPublicUuid;
use Database\Factories\InvoiceAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'order_revision_id',
    'kind',
    'amount_rupiah',
    'reason',
    'requested_by_membership_id',
    'approved_by_membership_id',
])]
#[UseFactory(InvoiceAdjustmentFactory::class)]
class InvoiceAdjustment extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderRevision(): BelongsTo
    {
        return $this->belongsTo(OrderRevision::class);
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
            'tenant_id' => 'integer',
            'invoice_id' => 'integer',
            'order_revision_id' => 'integer',
            'kind' => InvoiceAdjustmentKind::class,
            'amount_rupiah' => 'integer',
            'requested_by_membership_id' => 'integer',
            'approved_by_membership_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
