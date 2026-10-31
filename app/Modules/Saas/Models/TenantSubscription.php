<?php

namespace App\Modules\Saas\Models;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Support\Database\TenantModel;
use Database\Factories\TenantSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan_id', 'status', 'current_period_start', 'current_period_end', 'entitlements_snapshot', 'grace_until'])]
#[UseFactory(TenantSubscriptionFactory::class)]
class TenantSubscription extends TenantModel
{
    /** @use HasFactory<TenantSubscriptionFactory> */
    use HasFactory;

    protected $table = 'saas_subscriptions';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'plan_id' => 'integer',
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'entitlements_snapshot' => 'array',
            'grace_until' => 'datetime',
        ];
    }
}
