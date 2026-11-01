<?php

namespace App\Modules\Notifications\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
