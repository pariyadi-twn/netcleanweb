<?php

namespace App\Modules\Operations\Support;

use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Enums\BranchCalendarKind;
use App\Modules\Operations\Models\BranchCalendar;

final class BranchCalendarConfig
{
    /**
     * @param  iterable<BranchCalendar>  $rows
     * @return array<string, mixed>
     */
    public function present(Branch $branch, iterable $rows): array
    {
        $rows = collect($rows);
        $weekly = $rows
            ->where('kind', BranchCalendarKind::WeeklyHours)
            ->sortBy('weekday')
            ->map(fn (BranchCalendar $row): array => [
                'weekday' => $row->weekday,
                'is_open' => (bool) ($row->rules['is_open'] ?? false),
                'open_time' => $row->rules['open_time'] ?? null,
                'close_time' => $row->rules['close_time'] ?? null,
            ])
            ->values();
        $holidays = $rows
            ->where('kind', BranchCalendarKind::Holiday)
            ->map(fn (BranchCalendar $row): string => $row->local_date->toDateString())
            ->sort()
            ->values();
        $cutoff = $rows->firstWhere('kind', BranchCalendarKind::Cutoff);
        $updated = $rows->sortByDesc(fn (BranchCalendar $row): int => $row->updated_at?->getTimestamp() ?? 0)->first();

        return [
            'configured' => $weekly->isNotEmpty() && $cutoff !== null,
            'branch_id' => $branch->public_id,
            'timezone' => $branch->timezone,
            'weekly_hours' => $weekly->all(),
            'holidays' => $holidays->all(),
            'cutoff_time' => $cutoff?->rules['cutoff_time'] ?? null,
            'deadline_local_time' => $cutoff?->rules['deadline_local_time'] ?? null,
            'version' => $branch->version,
            'updated_at' => $updated?->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
