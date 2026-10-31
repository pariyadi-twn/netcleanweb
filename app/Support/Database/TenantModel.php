<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    use HasPublicUuid;

    protected $attributes = ['version' => 1];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty(['tenant_id', 'public_id'])) {
                throw new LogicException('Tenant and public identity are immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'tenant_id' => 'integer'];
    }
}
