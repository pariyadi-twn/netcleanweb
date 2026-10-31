<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'event_id',
    'consumer_name',
    'attempts',
    'lease_until',
    'last_dispatched_at',
    'next_attempt_at',
    'last_error_code',
])]
class EventDelivery extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class, 'event_id', 'event_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'attempts' => 'integer',
            'lease_until' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
