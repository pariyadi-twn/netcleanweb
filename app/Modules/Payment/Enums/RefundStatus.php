<?php

namespace App\Modules\Payment\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isReserved(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Processing], true);
    }
}
