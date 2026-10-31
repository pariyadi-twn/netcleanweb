<?php

namespace App\Modules\Operations\Models;

use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Enums\BranchCalendarKind;
use App\Support\Database\TenantModel;
use Database\Factories\BranchCalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'kind', 'local_date', 'weekday', 'rules'])]
#[UseFactory(BranchCalendarFactory::class)]
class BranchCalendar extends TenantModel
{
    /** @use HasFactory<BranchCalendarFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'branch_id' => 'integer',
            'kind' => BranchCalendarKind::class,
            'local_date' => 'date',
            'weekday' => 'integer',
            'rules' => 'array',
        ];
    }
}
