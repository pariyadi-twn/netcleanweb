<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\TenantStatus;
use App\Support\Database\HasPublicUuid;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone'])]
#[UseFactory(TenantFactory::class)]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasPublicUuid;

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TenantStatus::class, 'data_generation' => 'integer', 'version' => 'integer'];
    }
}
