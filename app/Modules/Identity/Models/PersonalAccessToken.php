<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
