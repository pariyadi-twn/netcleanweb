<?php

namespace App\Modules\Cash\Models;

use App\Modules\Cash\Enums\CashSessionStatus;
use App\Modules\Identity\Models\Branch;
use App\Support\Database\TenantModel;
use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['branch_id', 'code', 'is_active'])]
#[UseFactory(CashRegisterFactory::class)]
class CashRegister extends TenantModel
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function currentSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->where('status', CashSessionStatus::Open->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'branch_id' => 'integer', 'is_active' => 'boolean'];
    }
}
