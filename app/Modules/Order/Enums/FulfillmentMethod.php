<?php

namespace App\Modules\Order\Enums;

enum FulfillmentMethod: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
}
