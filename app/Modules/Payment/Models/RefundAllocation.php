<?php

namespace App\Modules\Payment\Models;

use App\Support\Database\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_id', 'payment_id', 'payment_allocation_id', 'amount_rupiah'])]
class RefundAllocation extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'refund_id' => 'integer',
            'payment_id' => 'integer',
            'payment_allocation_id' => 'integer',
            'amount_rupiah' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
