<?php

namespace App\Modules\Cash\Enums;

enum ExpenseApprovalStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
