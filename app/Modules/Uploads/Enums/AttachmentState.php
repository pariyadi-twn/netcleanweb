<?php

namespace App\Modules\Uploads\Enums;

enum AttachmentState: string
{
    case Pending = 'pending';
    case Quarantined = 'quarantined';
    case Ready = 'ready';
    case Rejected = 'rejected';
}
