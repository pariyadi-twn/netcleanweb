<?php

namespace App\Modules\Identity\Enums;

enum ProductionMode: string
{
    case Simple = 'simple';
    case Detailed = 'detailed';
}
