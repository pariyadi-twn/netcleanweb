<?php

namespace App\Modules\Order\Models;

use App\Support\Database\HasPublicUuid;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'order_line_id',
    'kind',
    'description',
    'unit',
    'quantity',
    'unit_price_rupiah',
    'amount_rupiah',
])]
#[UseFactory(InvoiceLineFactory::class)]
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'invoice_id' => 'integer',
            'order_line_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price_rupiah' => 'integer',
            'amount_rupiah' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
