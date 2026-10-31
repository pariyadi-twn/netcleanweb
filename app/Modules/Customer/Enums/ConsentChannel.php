<?php

namespace App\Modules\Customer\Enums;

enum ConsentChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Push = 'push';
}
