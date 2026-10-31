<?php

namespace App\Modules\Reporting\Enums;

enum ReportExportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
