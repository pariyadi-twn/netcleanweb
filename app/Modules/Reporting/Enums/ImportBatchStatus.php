<?php

namespace App\Modules\Reporting\Enums;

enum ImportBatchStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
