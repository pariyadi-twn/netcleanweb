<?php

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\AuditAction;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Jobs\ProcessOutboxDelivery;
use App\Modules\Operations\Models\ConsumerReceipt;
use App\Modules\Operations\Models\EventDelivery;
use App\Modules\Operations\Models\OutboxEvent;
use App\Modules\Operations\Outbox\OutboxConsumerRegistry;
use App\Modules\Operations\Outbox\OutboxDispatcher;
use App\Modules\Operations\Outbox\OutboxWriter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\AuditOutboxConsumer;
use Tests\Support\FailingOutboxConsumer;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_business_change_writes_event_and_delivery_atomically(): void
    {
        [$event, $delivery] = $this->scenario();

        $this->assertSame('membership.assignments_updated', $event->event_type);
        $this->assertSame('audit_trail', $delivery->consumer_name);
        $this->assertSame($event->tenant_id, $delivery->tenant_id);
        $this->assertSame($event->event_id, $delivery->event_id);
        $this->assertSame(0, $delivery->attempts);
        $this->assertArrayHasKey('membership_id', $event->payload);
        $this->assertArrayHasKey('role_ids', $event->payload);
        $this->assertArrayNotHasKey('password', $event->payload);
    }

    public function test_dispatcher_claims_delivery_and_recovers_when_receipt_is_missing(): void
    {
        [$event, $delivery] = $this->scenario();

        Queue::fake();
        $dispatcher = app(OutboxDispatcher::class);

        $this->assertSame(1, $dispatcher->dispatch());
        Queue::assertPushed(ProcessOutboxDelivery::class);

        $delivery->refresh();
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->last_dispatched_at);
        $this->assertNotNull($delivery->lease_until);

        $delivery->forceFill([
            'lease_until' => now()->subSecond(),
            'next_attempt_at' => now()->subSecond(),
        ])->save();

        $this->assertSame(1, $dispatcher->dispatch());
        Queue::assertPushed(ProcessOutboxDelivery::class, 2);
        $this->assertSame(2, $delivery->refresh()->attempts);
    }

    public function test_consumer_receipt_prevents_duplicate_database_effects_on_replay(): void
    {
        [$event, $delivery] = $this->scenario();

        Queue::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatch());

        $job = new ProcessOutboxDelivery($delivery->id, $delivery->tenant_id);
        $job->handle(app(OutboxConsumerRegistry::class), app(TenantScope::class));

        $this->assertSame(1, ConsumerReceipt::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OutboxEventApplied->value)->count());

        $job->handle(app(OutboxConsumerRegistry::class), app(TenantScope::class));

        $this->assertSame(1, ConsumerReceipt::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OutboxEventApplied->value)->count());

        Queue::fake();
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatch());
        $this->assertSame($event->event_id, $delivery->refresh()->event_id);
    }

    public function test_consumer_effect_and_receipt_are_atomic_when_consumer_fails(): void
    {
        [$event, $delivery] = $this->scenario(FailingOutboxConsumer::class);

        Queue::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatch());

        $job = new ProcessOutboxDelivery($delivery->id, $delivery->tenant_id);

        try {
            $job->handle(app(OutboxConsumerRegistry::class), app(TenantScope::class));
            $this->fail('The synthetic consumer should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic consumer failure.', $exception->getMessage());
        }

        $this->assertSame(0, ConsumerReceipt::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::OutboxEventApplied->value)->count());
        $this->assertSame($event->event_id, $delivery->refresh()->event_id);
    }

    public function test_outbox_writer_rejects_sensitive_payload_fields(): void
    {
        $tenant = Tenant::factory()->create();

        try {
            app(OutboxWriter::class)->record(
                tenantId: $tenant->id,
                eventType: 'membership.assignments_updated',
                aggregatePublicId: (string) Str::uuid(),
                payload: ['nested' => ['password' => 'plain-secret']],
            );
            $this->fail('Sensitive outbox payload must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('sensitive', $exception->getMessage());
        }

        $this->assertSame(0, OutboxEvent::query()->count());
    }

    /**
     * @return array{OutboxEvent, EventDelivery}
     */
    private function scenario(string $consumerClass = AuditOutboxConsumer::class): array
    {
        config([
            'outbox.consumers' => ['audit_trail' => $consumerClass],
            'outbox.subscriptions' => ['membership.assignments_updated' => ['audit_trail']],
        ]);

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();
        $actorMembership = TenantMembership::factory()->for($tenant)->for($actor)->create(['status' => 'active']);
        $target = TenantMembership::factory()->for($tenant)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create();
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->atBranch($branch)->create();

        app(UpdateMembershipAssignments::class)->handle(
            $target,
            [$role->id],
            [$branch->id],
            [$location->id],
            $actor,
            'Outbox test assignment',
        );

        return [OutboxEvent::query()->sole(), EventDelivery::query()->sole()];
    }
}
