<?php

namespace App\Modules\Order\Enums;

enum DiscountApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
