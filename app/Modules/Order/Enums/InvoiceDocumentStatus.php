<?php

namespace App\Modules\Order\Enums;

enum InvoiceDocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';
}
