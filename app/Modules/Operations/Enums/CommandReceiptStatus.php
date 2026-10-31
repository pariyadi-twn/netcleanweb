<?php

namespace App\Modules\Operations\Enums;

enum CommandReceiptStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
