<?php

namespace App\Modules\Cash\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
