<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Enums\TurnaroundMode;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Enums\BranchCalendarKind;
use App\Modules\Operations\Models\BranchCalendar;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CalculateDeadline
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        Branch $branch,
        Service $service,
        PriceTier $tier = PriceTier::Regular,
        ?string $acceptedAt = null,
    ): array {
        $rows = BranchCalendar::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->id)
            ->get();
        $weekly = $rows->where('kind', BranchCalendarKind::WeeklyHours)->keyBy('weekday');
        $holidays = $rows->where('kind', BranchCalendarKind::Holiday)
            ->map(fn (BranchCalendar $row): string => $row->local_date->toDateString())
            ->flip();
        $cutoff = $rows->firstWhere('kind', BranchCalendarKind::Cutoff);

        $timezone = new DateTimeZone($branch->timezone);
        $acceptedUtc = $this->acceptedAt($acceptedAt, $timezone);
        $acceptedLocal = $acceptedUtc->setTimezone($timezone);
        $turnaround = $tier === PriceTier::Express
            ? $service->express_turnaround
            : $service->regular_turnaround;

        if ($service->sla_mode === TurnaroundMode::ElapsedHours) {
            $dueUtc = $acceptedUtc->addHours($turnaround);

            return $this->result(
                branch: $branch,
                service: $service,
                tier: $tier,
                turnaround: $turnaround,
                acceptedUtc: $acceptedUtc,
                dueUtc: $dueUtc,
                timezone: $timezone,
                startDate: null,
                cutoffApplied: false,
                startedNextWorkingDay: false,
                rows: $rows,
                cutoff: $cutoff,
            );
        }

        $this->ensureConfigured($weekly, $cutoff);
        $cutoffMinutes = $this->minutes((string) $cutoff->rules['cutoff_time']);
        $deadlineMinutes = $this->minutes((string) $cutoff->rules['deadline_local_time']);

        if ($cutoffMinutes === null || $deadlineMinutes === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['The branch cutoff calendar is invalid.'],
            ]);
        }

        $cutoffLocal = $acceptedLocal->setTime(intdiv($cutoffMinutes, 60), $cutoffMinutes % 60);
        $isWorkingDay = $this->isWorkingDay($acceptedLocal, $weekly, $holidays);
        $afterCutoff = $acceptedLocal >= $cutoffLocal;
        $startsNextWorkingDay = ! $isWorkingDay || $afterCutoff;
        $start = $startsNextWorkingDay
            ? $this->nextWorkingDay($acceptedLocal->addDay(), $weekly, $holidays)
            : $acceptedLocal;

        $dueDate = $start;
        for ($day = 0; $day < $turnaround; $day++) {
            $dueDate = $this->nextWorkingDay($dueDate->addDay(), $weekly, $holidays);
        }

        $dueLocal = $dueDate->setTime(intdiv($deadlineMinutes, 60), $deadlineMinutes % 60);

        return $this->result(
            branch: $branch,
            service: $service,
            tier: $tier,
            turnaround: $turnaround,
            acceptedUtc: $acceptedUtc,
            dueUtc: $dueLocal->utc(),
            timezone: $timezone,
            startDate: $start->toDateString(),
            cutoffApplied: $isWorkingDay && $afterCutoff,
            startedNextWorkingDay: $startsNextWorkingDay,
            rows: $rows,
            cutoff: $cutoff,
        );
    }

    /**
     * @param  Collection<int, BranchCalendar>  $weekly
     * @param  Collection<string, BranchCalendar>  $holidays
     */
    private function isWorkingDay(CarbonImmutable $date, Collection $weekly, Collection $holidays): bool
    {
        $day = $weekly->get($date->dayOfWeek);

        return $day !== null
            && (bool) ($day->rules['is_open'] ?? false)
            && ! $holidays->has($date->toDateString());
    }

    /**
     * @param  Collection<int, BranchCalendar>  $weekly
     * @param  Collection<string, BranchCalendar>  $holidays
     */
    private function nextWorkingDay(CarbonImmutable $date, Collection $weekly, Collection $holidays): CarbonImmutable
    {
        while (! $this->isWorkingDay($date, $weekly, $holidays)) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function acceptedAt(?string $acceptedAt, DateTimeZone $timezone): CarbonImmutable
    {
        if ($acceptedAt === null) {
            return CarbonImmutable::now()->utc();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $acceptedAt) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $acceptedAt, $timezone)->startOfDay()->utc();
        }

        return CarbonImmutable::parse($acceptedAt)->utc();
    }

    /**
     * @param  Collection<int, BranchCalendar>  $weekly
     * @param  Collection<string, BranchCalendar>  $holidays
     * @param  Collection<int, BranchCalendar>  $rows
     * @return array<string, mixed>
     */
    private function result(
        Branch $branch,
        Service $service,
        PriceTier $tier,
        int $turnaround,
        CarbonImmutable $acceptedUtc,
        CarbonImmutable $dueUtc,
        DateTimeZone $timezone,
        ?string $startDate,
        bool $cutoffApplied,
        bool $startedNextWorkingDay,
        Collection $rows,
        ?BranchCalendar $cutoff,
    ): array {
        $acceptedLocal = $acceptedUtc->setTimezone($timezone);
        $dueLocal = $dueUtc->setTimezone($timezone);
        $updated = $rows->sortByDesc(fn (BranchCalendar $row): int => $row->updated_at?->getTimestamp() ?? 0)->first();

        return [
            'branch_id' => $branch->public_id,
            'service_id' => $service->public_id,
            'tier' => $tier->value,
            'mode' => $service->sla_mode->value,
            'turnaround' => $turnaround,
            'working_days' => $service->sla_mode === TurnaroundMode::WorkingDays ? $turnaround : null,
            'elapsed_hours' => $service->sla_mode === TurnaroundMode::ElapsedHours ? $turnaround : null,
            'accepted_at' => $acceptedUtc->format('Y-m-d\TH:i:s\Z'),
            'accepted_at_local' => $acceptedLocal->format('Y-m-d\TH:i:sP'),
            'due_at' => $dueUtc->format('Y-m-d\TH:i:s\Z'),
            'due_at_local' => $dueLocal->format('Y-m-d\TH:i:sP'),
            'start_date' => $startDate,
            'cutoff_applied' => $cutoffApplied,
            'started_next_working_day' => $startedNextWorkingDay,
            'cutoff_time' => $cutoff?->rules['cutoff_time'],
            'deadline_local_time' => $cutoff?->rules['deadline_local_time'],
            'timezone' => $branch->timezone,
            'branch_version' => $branch->version,
            'calendar_updated_at' => $updated?->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @param  Collection<int, BranchCalendar>  $weekly
     */
    private function ensureConfigured(Collection $weekly, ?BranchCalendar $cutoff): void
    {
        $hasOpenDay = $weekly->contains(fn (BranchCalendar $day): bool => (bool) ($day->rules['is_open'] ?? false));

        if (! $hasOpenDay || $cutoff === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['The branch calendar must be configured before calculating a deadline.'],
            ]);
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
