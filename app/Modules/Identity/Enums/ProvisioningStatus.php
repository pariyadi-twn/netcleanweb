<?php

namespace App\Modules\Identity\Enums;

enum ProvisioningStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
