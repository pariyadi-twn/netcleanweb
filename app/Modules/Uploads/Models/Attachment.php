<?php

namespace App\Modules\Uploads\Models;

use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Uploads\Enums\AttachmentState;
use App\Support\Database\TenantModel;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uploaded_by_membership_id',
    'object_key',
    'thumbnail_key',
    'mime_type',
    'size_bytes',
    'expected_sha256',
    'sha256',
    'width',
    'height',
    'original_name',
    'state',
    'purpose',
])]
#[UseFactory(AttachmentFactory::class)]
class Attachment extends TenantModel
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'uploaded_by_membership_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'uploaded_by_membership_id' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'state' => AttachmentState::class,
        ];
    }
}
