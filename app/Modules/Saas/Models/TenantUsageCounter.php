<?php

namespace App\Modules\Saas\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'metric_code', 'period', 'used', 'reserved'])]
class TenantUsageCounter extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'period' => 'date',
            'used' => 'integer',
            'reserved' => 'integer',
        ];
    }
}
