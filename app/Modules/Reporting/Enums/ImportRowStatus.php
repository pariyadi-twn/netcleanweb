<?php

namespace App\Modules\Reporting\Enums;

enum ImportRowStatus: string
{
    case Validated = 'validated';
    case Imported = 'imported';
    case Duplicate = 'duplicate';
    case Invalid = 'invalid';
}
