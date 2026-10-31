<?php

namespace App\Modules\Catalog\Enums;

enum TurnaroundMode: string
{
    case WorkingDays = 'working_days';
    case ElapsedHours = 'elapsed_hours';
}
