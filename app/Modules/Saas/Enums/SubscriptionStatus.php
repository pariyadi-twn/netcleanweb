<?php

namespace App\Modules\Saas\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case ReadOnly = 'read_only';
    case Cancelled = 'cancelled';
}
