<?php

namespace App\Modules\Order\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Enums\InvoiceDocumentStatus;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Support\Database\TenantModel;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'customer_id',
    'sales_branch_id',
    'invoice_number',
    'document_status',
    'currency',
    'original_total_rupiah',
    'issued_at',
    'due_at',
])]
#[UseFactory(InvoiceFactory::class)]
class Invoice extends TenantModel
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sales_branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function totalRupiah(): int
    {
        return (int) $this->original_total_rupiah + (int) $this->adjustments()->sum('amount_rupiah');
    }

    public function netAllocatedRupiah(): int
    {
        $this->loadMissing(['allocations.reversals', 'allocations.refundAllocations.refund']);

        return (int) $this->allocations->sum(function (PaymentAllocation $allocation): int {
            $refunded = $allocation->refundAllocations
                ->filter(fn ($refundAllocation): bool => $refundAllocation->refund?->status->value === 'succeeded')
                ->sum('amount_rupiah');

            return (int) $allocation->amount_rupiah - (int) $allocation->reversals->sum('amount_rupiah') - (int) $refunded;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'sales_branch_id' => 'integer',
            'document_status' => InvoiceDocumentStatus::class,
            'original_total_rupiah' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
