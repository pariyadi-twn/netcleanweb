<?php

namespace Tests\Feature\Operations;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Operations\Actions\GetDeadlinePromise;
use App\Modules\Operations\Actions\RecordDeadlinePromise;
use App\Modules\Operations\Actions\ReviseDeadlinePromise;
use App\Modules\Operations\Models\BranchCalendar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeadlinePromiseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_initial_and_revised_deadlines_are_traceable_by_actor_reason_and_time(): void
    {
        [$tenant, $user, $branch, $service] = $this->scenario();
        $promiseId = (string) Str::uuid();

        $initial = app(RecordDeadlinePromise::class)->handle(
            $branch,
            $service,
            PriceTier::Regular,
            $promiseId,
            '2026-11-02T14:00:00+07:00',
            $user,
            'Initial promise',
        );

        $this->assertSame('2026-11-04T13:00:00Z', $initial['due_at']);

        $revised = app(ReviseDeadlinePromise::class)->handle(
            $branch,
            $service,
            PriceTier::Regular,
            $promiseId,
            $initial['due_at'],
            '2026-11-03T14:00:00+07:00',
            $user,
            'Customer requested a later date',
        );

        $this->assertSame('2026-11-06T13:00:00Z', $revised['due_at']);

        $trace = app(GetDeadlinePromise::class)->handle($tenant->id, $promiseId);

        $this->assertSame('2026-11-04T13:00:00Z', $trace['due_at_original']);
        $this->assertSame('2026-11-06T13:00:00Z', $trace['due_at_current']);
        $this->assertCount(2, $trace['history']);
        $this->assertSame(AuditAction::DeadlinePromiseRecorded->value, $trace['history'][0]['action']);
        $this->assertNull($trace['history'][0]['old_due_at']);
        $this->assertSame('Initial promise', $trace['history'][0]['reason']);
        $this->assertSame($user->id, $trace['history'][0]['actor_user_id']);
        $this->assertSame(AuditAction::DeadlineRevised->value, $trace['history'][1]['action']);
        $this->assertSame('2026-11-04T13:00:00Z', $trace['history'][1]['old_due_at']);
        $this->assertSame('2026-11-06T13:00:00Z', $trace['history'][1]['new_due_at']);
        $this->assertSame('Customer requested a later date', $trace['history'][1]['reason']);

        $this->assertSame(2, AuditLog::query()->where('subject_type', 'deadline_promise')->count());
    }

    public function test_stale_revision_missing_promise_and_empty_reason_are_rejected(): void
    {
        [$tenant, $user, $branch, $service] = $this->scenario();
        $missingId = (string) Str::uuid();

        try {
            app(GetDeadlinePromise::class)->handle($tenant->id, $missingId);
            $this->fail('A missing deadline promise must not be found.');
        } catch (ApiException $exception) {
            $this->assertSame('DEADLINE_PROMISE_NOT_FOUND', $exception->errorCode);
            $this->assertSame(404, $exception->status);
        }

        $promiseId = (string) Str::uuid();
        app(RecordDeadlinePromise::class)->handle(
            $branch,
            $service,
            PriceTier::Regular,
            $promiseId,
            '2026-11-02T14:00:00+07:00',
            $user,
        );

        try {
            app(ReviseDeadlinePromise::class)->handle(
                $branch,
                $service,
                PriceTier::Regular,
                $promiseId,
                '2026-11-05T13:00:00Z',
                '2026-11-03T14:00:00+07:00',
                $user,
                'Stale revision',
            );
            $this->fail('A stale deadline revision must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame('VERSION_CONFLICT', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }

        try {
            app(ReviseDeadlinePromise::class)->handle(
                $branch,
                $service,
                PriceTier::Regular,
                $promiseId,
                '2026-11-04T13:00:00Z',
                '2026-11-03T14:00:00+07:00',
                $user,
                '   ',
            );
            $this->fail('A deadline revision without a reason must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
    }

    /** @return array{Tenant, User, Branch, Service} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $branch = Branch::factory()->for($tenant)->create();

        foreach (range(0, 6) as $weekday) {
            BranchCalendar::factory()->forBranch($branch)->create([
                'weekday' => $weekday,
                'rules' => $weekday === 0
                    ? ['is_open' => false]
                    : ['is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
            ]);
        }

        BranchCalendar::factory()->forBranch($branch)->cutoff('15:00', '20:00')->create();
        BranchCalendar::factory()->forBranch($branch)->holiday('2026-11-05')->create();

        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create();

        return [$tenant, $user, $branch, $service];
    }
}
