<?php

namespace App\Modules\Production\Enums;

enum ProductionTaskStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Blocked = 'blocked';
    case Skipped = 'skipped';
}
