<?php

namespace App\Modules\Identity\Models;

use App\Support\Database\TenantModel;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name'])]
#[UseFactory(RoleFactory::class)]
class Role extends TenantModel
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'is_system' => 'boolean'];
    }
}
