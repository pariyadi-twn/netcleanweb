<?php

namespace App\Modules\Cash\Models;

use App\Support\Database\HasPublicUuid;
use Database\Factories\CashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cash_session_id', 'kind', 'amount_rupiah', 'reference_type', 'reference_id', 'reverses_entry_id'])]
#[UseFactory(CashEntryFactory::class)]
class CashEntry extends Model
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
            'amount_rupiah' => 'integer',
            'reference_id' => 'integer',
            'reverses_entry_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
