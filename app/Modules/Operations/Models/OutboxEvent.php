<?php

namespace App\Modules\Operations\Models;

use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'event_id', 'event_type', 'aggregate_public_id', 'payload'])]
class OutboxEvent extends Model
{
    public const UPDATED_AT = null;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EventDelivery::class, 'event_id', 'event_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'payload' => 'array', 'created_at' => 'datetime'];
    }
}
