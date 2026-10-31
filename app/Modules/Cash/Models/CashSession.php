<?php

namespace App\Modules\Cash\Models;

use App\Modules\Cash\Enums\CashSessionStatus;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Database\TenantModel;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cash_register_id', 'cashier_membership_id', 'opened_at', 'closed_at', 'opening_rupiah', 'counted_closing_rupiah', 'status', 'discrepancy_reason'])]
#[UseFactory(CashSessionFactory::class)]
class CashSession extends TenantModel
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'cashier_membership_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CashSessionEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'cash_register_id' => 'integer',
            'cashier_membership_id' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_rupiah' => 'integer',
            'counted_closing_rupiah' => 'integer',
            'status' => CashSessionStatus::class,
        ];
    }
}
