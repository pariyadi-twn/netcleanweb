<?php

namespace Tests\Feature\Operations;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Operations\Jobs\ProcessOutboxDelivery;
use App\Modules\Operations\Models\EventDelivery;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AuditOutboxConsumer;
use Tests\TestCase;

class OutboxDispatchCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        DB::setDefaultConnection((string) config('database.default'));
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_runtime_command_claims_a_bounded_cross_tenant_batch_without_rls_context(): void
    {
        config([
            'outbox.consumers' => ['audit_trail' => AuditOutboxConsumer::class],
            'outbox.subscriptions' => ['membership.assignments_updated' => ['audit_trail']],
        ]);
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();
        $writer = app(OutboxWriter::class);
        $writer->record($firstTenant->id, 'membership.assignments_updated', (string) Str::uuid(), ['tenant' => 'first']);
        $writer->record($secondTenant->id, 'membership.assignments_updated', (string) Str::uuid(), ['tenant' => 'second']);
        $firstDelivery = EventDelivery::query()->oldest('id')->firstOrFail();
        Queue::fake([ProcessOutboxDelivery::class]);
        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_runtime');

        try {
            $this->assertSame(0, DB::table('event_deliveries')->count());
            $this->artisan('outbox:dispatch --limit=1')
                ->expectsOutput('Dispatched 1 outbox delivery job(s).')
                ->assertSuccessful();
        } finally {
            DB::setDefaultConnection($previousConnection);
        }

        Queue::assertPushedOn('operations', ProcessOutboxDelivery::class, function (ProcessOutboxDelivery $job) use ($firstDelivery): bool {
            return $job->deliveryId === $firstDelivery->id && $job->tenantId === $firstDelivery->tenant_id;
        });
        $this->assertSame(1, $firstDelivery->refresh()->attempts);
        $this->assertSame(1, EventDelivery::query()->where('attempts', 0)->count());
    }

    public function test_command_rejects_an_invalid_limit_without_dispatching_jobs(): void
    {
        Queue::fake([ProcessOutboxDelivery::class]);

        $this->artisan('outbox:dispatch --limit=1001')
            ->expectsOutput('The --limit option must be an integer between 1 and 1000.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }
}
