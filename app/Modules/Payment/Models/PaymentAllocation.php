<?php

namespace App\Modules\Payment\Models;

use App\Modules\Order\Models\Invoice;
use App\Support\Database\HasPublicUuid;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payment_id', 'invoice_id', 'amount_rupiah'])]
#[UseFactory(PaymentAllocationFactory::class)]
class PaymentAllocation extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(AllocationReversal::class);
    }

    public function refundAllocations(): HasMany
    {
        return $this->hasMany(RefundAllocation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'payment_id' => 'integer',
            'invoice_id' => 'integer',
            'amount_rupiah' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
