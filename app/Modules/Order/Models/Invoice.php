<?php

namespace App\Modules\Order\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Enums\InvoiceDocumentStatus;
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
