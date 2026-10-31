<?php

namespace App\Modules\Customer\Models;

use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use App\Support\Database\HasPublicUuid;
use Database\Factories\CustomerConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'purpose', 'channel', 'decision', 'source', 'occurred_at'])]
#[UseFactory(CustomerConsentFactory::class)]
class CustomerConsent extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_id' => 'integer',
            'purpose' => ConsentPurpose::class,
            'channel' => ConsentChannel::class,
            'decision' => ConsentDecision::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
