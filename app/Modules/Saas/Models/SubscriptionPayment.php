<?php

namespace App\Modules\Saas\Models;

use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_invoice_id',
    'amount_rupiah',
    'reference',
    'verified_at',
    'verified_by_user_id',
])]
class SubscriptionPayment extends TenantModel
{
    protected $table = 'saas_subscription_payments';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'subscription_invoice_id' => 'integer',
            'amount_rupiah' => 'integer',
            'verified_at' => 'datetime',
            'verified_by_user_id' => 'integer',
        ];
    }
}
