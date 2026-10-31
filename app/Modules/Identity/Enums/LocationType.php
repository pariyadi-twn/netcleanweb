<?php

namespace App\Modules\Identity\Enums;

enum LocationType: string
{
    case Outlet = 'outlet';
    case Plant = 'plant';
    case Warehouse = 'warehouse';
    case Rack = 'rack';
}
