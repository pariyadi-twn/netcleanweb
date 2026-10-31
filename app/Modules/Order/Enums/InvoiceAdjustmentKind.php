<?php

namespace App\Modules\Order\Enums;

enum InvoiceAdjustmentKind: string
{
    case DebitNote = 'debit_note';
    case CreditNote = 'credit_note';
}
