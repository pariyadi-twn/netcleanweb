<?php

namespace App\Modules\Order\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\ReceiptPrintStatus;
use App\Support\Database\HasPublicUuid;
use Database\Factories\ReceiptPrintAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'actor_membership_id', 'status', 'receipt_hash', 'error_code'])]
#[UseFactory(ReceiptPrintAttemptFactory::class)]
class ReceiptPrintAttempt extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'actor_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'order_id' => 'integer',
            'actor_membership_id' => 'integer',
            'status' => ReceiptPrintStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
