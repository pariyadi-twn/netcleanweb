<?php

namespace App\Modules\Operations\Enums;

enum BranchCalendarKind: string
{
    case WeeklyHours = 'weekly_hours';
    case Holiday = 'holiday';
    case Cutoff = 'cutoff';
}
