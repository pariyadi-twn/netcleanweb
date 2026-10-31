<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Support\Database\HasPublicUuid;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'installation_hash', 'last_seen_at', 'revoked_at'])]
#[UseFactory(DeviceFactory::class)]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
