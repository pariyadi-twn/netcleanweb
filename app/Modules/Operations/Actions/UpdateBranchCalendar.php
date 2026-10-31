<?php

namespace App\Modules\Operations\Actions;

use App\Models\User;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Enums\BranchCalendarKind;
use App\Modules\Operations\Models\BranchCalendar;
use App\Modules\Operations\Support\BranchCalendarConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateBranchCalendar
{
    public function __construct(
        private readonly AuditRecorder $auditor,
        private readonly BranchCalendarConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function handle(Branch $branch, array $attributes, ?User $actor = null, ?string $reason = null): array
    {
        $weekly = collect($attributes['weekly_hours'])->sortBy('weekday')->values();
        $holidays = collect($attributes['holidays'] ?? [])->sort()->values();
        $this->validatePeriod($weekly, $holidays, (string) $attributes['cutoff_time'], (string) $attributes['deadline_local_time']);

        return DB::transaction(function () use ($branch, $weekly, $holidays, $attributes, $actor, $reason): array {
            $lockedBranch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $before = $this->config->present($lockedBranch, $lockedBranch->calendars()->get());

            BranchCalendar::query()
                ->where('tenant_id', $lockedBranch->tenant_id)
                ->where('branch_id', $lockedBranch->id)
                ->delete();

            foreach ($weekly as $day) {
                $calendar = new BranchCalendar([
                    'branch_id' => $lockedBranch->id,
                    'kind' => BranchCalendarKind::WeeklyHours,
                    'weekday' => $day['weekday'],
                    'local_date' => null,
                    'rules' => [
                        'is_open' => (bool) $day['is_open'],
                        'open_time' => $day['is_open'] ? $day['open_time'] : null,
                        'close_time' => $day['is_open'] ? $day['close_time'] : null,
                    ],
                ]);
                $calendar->tenant_id = $lockedBranch->tenant_id;
                $calendar->save();
            }

            foreach ($holidays as $holiday) {
                $calendar = new BranchCalendar([
                    'branch_id' => $lockedBranch->id,
                    'kind' => BranchCalendarKind::Holiday,
                    'weekday' => null,
                    'local_date' => $holiday,
                    'rules' => [],
                ]);
                $calendar->tenant_id = $lockedBranch->tenant_id;
                $calendar->save();
            }

            $cutoff = new BranchCalendar([
                'branch_id' => $lockedBranch->id,
                'kind' => BranchCalendarKind::Cutoff,
                'weekday' => null,
                'local_date' => null,
                'rules' => [
                    'cutoff_time' => $attributes['cutoff_time'],
                    'deadline_local_time' => $attributes['deadline_local_time'],
                ],
            ]);
            $cutoff->tenant_id = $lockedBranch->tenant_id;
            $cutoff->save();

            $lockedBranch->forceFill(['version' => $lockedBranch->version + 1])->save();
            $lockedBranch->refresh();
            $after = $this->config->present($lockedBranch, $lockedBranch->calendars()->get());

            $this->auditor->record(
                tenantId: $lockedBranch->tenant_id,
                action: AuditAction::BranchCalendarUpdated,
                subjectType: AuditRecorder::SUBJECT_BRANCH,
                subjectPublicId: $lockedBranch->public_id,
                changes: ['before' => $before, 'after' => $after],
                actor: $actor,
                reason: $reason,
            );

            return $after;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $weekly
     * @param  Collection<int, string>  $holidays
     */
    private function validatePeriod($weekly, $holidays, string $cutoffTime, string $deadlineTime): void
    {
        $errors = [];
        $weekdays = $weekly->pluck('weekday')->map(fn ($day): int => (int) $day)->all();

        if (count($weekdays) !== 7 || count(array_unique($weekdays)) !== 7 || min($weekdays) < 0 || max($weekdays) > 6) {
            $errors['weekly_hours'][] = 'Weekly hours must contain exactly one row for each weekday from 0 to 6.';
        }

        if ($holidays->duplicates()->isNotEmpty()) {
            $errors['holidays'][] = 'Holiday dates must be unique.';
        }

        $cutoff = $this->minutes($cutoffTime);
        $deadline = $this->minutes($deadlineTime);

        if ($cutoff >= $deadline) {
            $errors['deadline_local_time'][] = 'The deadline time must be later than the cutoff time.';
        }

        $openDays = $weekly->filter(fn (array $day): bool => (bool) ($day['is_open'] ?? false));

        if ($openDays->isEmpty()) {
            $errors['weekly_hours'][] = 'At least one weekday must be open.';
        }

        foreach ($openDays as $index => $day) {
            $open = $this->minutes((string) ($day['open_time'] ?? ''));
            $close = $this->minutes((string) ($day['close_time'] ?? ''));

            if ($open === null || $close === null || $open >= $close) {
                $errors['weekly_hours.'.$index.'.close_time'][] = 'The closing time must be later than the opening time.';

                continue;
            }

            if ($cutoff < $open || $cutoff > $close) {
                $errors['cutoff_time'][] = 'The cutoff time must fall within each open day.';
            }

            if ($deadline < $open || $deadline > $close) {
                $errors['deadline_local_time'][] = 'The deadline time must fall within each open day.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function minutes(string $time): ?int
    {
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
