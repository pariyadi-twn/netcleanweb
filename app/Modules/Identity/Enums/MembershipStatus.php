<?php

namespace App\Modules\Identity\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Disabled = 'disabled';
}
