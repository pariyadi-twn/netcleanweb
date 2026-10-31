<?php

namespace App\Modules\Order\Enums;

enum OrderRevisionStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';
}
