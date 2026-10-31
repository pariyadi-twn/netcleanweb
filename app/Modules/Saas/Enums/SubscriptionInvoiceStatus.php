<?php

namespace App\Modules\Saas\Enums;

enum SubscriptionInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
