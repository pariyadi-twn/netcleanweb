<?php

namespace App\Modules\Identity\Enums;

enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
