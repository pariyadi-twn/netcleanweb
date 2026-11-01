<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Order\Models\Order;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'order_id',
    'channel',
    'business_key',
    'recipient_ref',
    'subject',
    'message',
    'payload',
    'status',
    'provider_reference',
    'attempts',
    'last_error_code',
    'next_attempt_at',
    'sent_at',
])]
class NotificationDelivery extends TenantModel
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'event_id' => 'string',
            'order_id' => 'integer',
            'channel' => NotificationChannel::class,
            'recipient_ref' => 'encrypted',
            'payload' => 'array',
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
