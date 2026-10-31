<?php

namespace App\Modules\Cash\Models;

use App\Support\Database\HasPublicUuid;
use Database\Factories\CashSessionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cash_session_id', 'event_type', 'actor_membership_id', 'payload', 'reason'])]
#[UseFactory(CashSessionEventFactory::class)]
class CashSessionEvent extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'cash_session_id' => 'integer',
            'actor_membership_id' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
