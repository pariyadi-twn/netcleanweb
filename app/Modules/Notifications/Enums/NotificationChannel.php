<?php

namespace App\Modules\Notifications\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Push = 'push';
}
