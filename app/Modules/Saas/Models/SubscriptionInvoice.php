<?php

namespace App\Modules\Saas\Models;

use App\Modules\Saas\Enums\SubscriptionInvoiceStatus;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'subscription_id',
    'platform_invoice_number',
    'kind',
    'amount_rupiah',
    'status',
    'period_start',
    'period_end',
    'issued_at',
    'due_at',
])]
class SubscriptionInvoice extends TenantModel
{
    protected $table = 'saas_subscription_invoices';

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_invoice_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'subscription_id' => 'integer',
            'amount_rupiah' => 'integer',
            'status' => SubscriptionInvoiceStatus::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
