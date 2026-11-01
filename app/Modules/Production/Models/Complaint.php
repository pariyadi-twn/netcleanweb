<?php

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\InvoiceAdjustment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Uploads\Models\Attachment;
use App\Support\Database\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'order_id',
    'order_line_id',
    'kind',
    'status',
    'description',
    'resolution',
    'opened_by_membership_id',
    'resolved_by_membership_id',
    'resolved_at',
    'approved_adjustment_id',
    'rewash_attempt_no',
])]
class Complaint extends TenantModel
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'opened_by_membership_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'resolved_by_membership_id');
    }

    public function approvedAdjustment(): BelongsTo
    {
        return $this->belongsTo(InvoiceAdjustment::class, 'approved_adjustment_id');
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class, 'complaint_attachments', 'complaint_id', 'attachment_id')
            ->withPivot('tenant_id', 'created_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'order_id' => 'integer',
            'order_line_id' => 'integer',
            'opened_by_membership_id' => 'integer',
            'resolved_by_membership_id' => 'integer',
            'resolved_at' => 'datetime',
            'approved_adjustment_id' => 'integer',
            'rewash_attempt_no' => 'integer',
        ];
    }
}
