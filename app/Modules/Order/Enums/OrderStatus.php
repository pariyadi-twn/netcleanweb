<?php

namespace App\Modules\Order\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case PartiallyReady = 'partially_ready';
    case Ready = 'ready';
    case PartiallyHandedOver = 'partially_handed_over';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
