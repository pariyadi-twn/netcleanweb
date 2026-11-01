<?php

namespace Tests\Feature\Ops;

use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Notifications\Jobs\SendNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Operations\Jobs\ProcessOutboxDelivery;
use App\Modules\Operations\Models\EventDelivery;
use App\Modules\Operations\Outbox\OutboxConsumerRegistry;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RestoreReplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_replay_after_worker_crash_does_not_duplicate_notification_effect(): void
    {
        Queue::fake();
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->for($tenant)->create();
        $customer = new Customer(['name' => 'Replay Customer', 'normalized_phone' => '081200000001', 'email' => 'replay@example.com']);
        $customer->tenant_id = $tenant->id;
        $customer->save();
        CustomerConsent::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'purpose' => ConsentPurpose::ServiceContact, 'channel' => ConsentChannel::Email,
            'decision' => ConsentDecision::Granted, 'source' => 'cashier', 'occurred_at' => now(),
        ]);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id, 'customer_id' => $customer->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'customer_id' => $customer->id,
            'sales_branch_id' => $branch->id, 'operational_status' => 'accepted',
        ]);

        app(OutboxWriter::class)->record(
            tenantId: $tenant->id,
            eventType: 'order.accepted',
            aggregatePublicId: $order->public_id,
            payload: ['order_id' => $order->public_id, 'status' => 'accepted'],
        );
        $delivery = EventDelivery::query()->where('tenant_id', $tenant->id)->sole();
        $job = new ProcessOutboxDelivery($delivery->id, $tenant->id);

        $job->handle(app(OutboxConsumerRegistry::class), app(TenantScope::class));
        $job->handle(app(OutboxConsumerRegistry::class), app(TenantScope::class));

        $this->assertDatabaseCount('consumer_receipts', 1);
        $this->assertSame(1, NotificationDelivery::query()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('notification_deliveries', ['tenant_id' => $tenant->id, 'status' => 'queued']);
        Queue::assertPushed(SendNotificationDelivery::class, 1);
    }
}
