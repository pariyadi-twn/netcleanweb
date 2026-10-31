<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Enums\BranchCalendarKind;
use App\Modules\Operations\Models\BranchCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchCalendar>
 */
class BranchCalendarFactory extends Factory
{
    protected $model = BranchCalendar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'tenant_id' => fn (array $attributes): int => Branch::query()->findOrFail($attributes['branch_id'])->tenant_id,
            'kind' => BranchCalendarKind::WeeklyHours,
            'local_date' => null,
            'weekday' => 1,
            'rules' => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);
    }

    public function holiday(string $date): static
    {
        return $this->state(fn (): array => [
            'kind' => BranchCalendarKind::Holiday,
            'local_date' => $date,
            'weekday' => null,
            'rules' => [],
        ]);
    }

    public function cutoff(string $time, string $deadline): static
    {
        return $this->state(fn (): array => [
            'kind' => BranchCalendarKind::Cutoff,
            'local_date' => null,
            'weekday' => null,
            'rules' => ['cutoff_time' => $time, 'deadline_local_time' => $deadline],
        ]);
    }
}
