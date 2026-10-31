<?php

namespace App\Modules\Customer\Enums;

enum ConsentDecision: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
