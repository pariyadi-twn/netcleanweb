<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'event_id', 'consumer_name', 'applied_at'])]
class ConsumerReceipt extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'applied_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
