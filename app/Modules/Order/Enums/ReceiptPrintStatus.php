<?php

namespace App\Modules\Order\Enums;

enum ReceiptPrintStatus: string
{
    case Printed = 'printed';
    case Failed = 'failed';
}
