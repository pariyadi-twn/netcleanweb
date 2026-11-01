<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Customer\Enums\ConsentChannel;
use App\Modules\Customer\Enums\ConsentDecision;
use App\Modules\Customer\Enums\ConsentPurpose;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerConsent;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Notifications\Consumers\NotificationIntentConsumer;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Jobs\SendNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Notifications\NotificationSender;
use App\Modules\Operations\Outbox\OutboxWriter;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_service_notice_uses_granted_consent_and_is_idempotent(): void
    {
        [$tenant, $order, $customer] = $this->scenario();
        CustomerConsent::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'purpose' => ConsentPurpose::ServiceContact, 'channel' => ConsentChannel::Email,
            'decision' => ConsentDecision::Granted, 'source' => 'cashier', 'occurred_at' => now(),
        ]);
        CustomerConsent::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'purpose' => ConsentPurpose::Marketing, 'channel' => ConsentChannel::Email,
            'decision' => ConsentDecision::Withdrawn, 'source' => 'customer', 'occurred_at' => now(),
        ]);

        Queue::fake();
        $event = app(OutboxWriter::class)->record(
            tenantId: $tenant->id,
            eventType: 'order.accepted',
            aggregatePublicId: $order->public_id,
            payload: ['order_id' => $order->public_id, 'status' => 'accepted'],
        );

        app(TenantScope::class)->forTenantId($tenant->id, 0, fn () => app(NotificationIntentConsumer::class)->handle($event));
        app(TenantScope::class)->forTenantId($tenant->id, 0, fn () => app(NotificationIntentConsumer::class)->handle($event));

        $delivery = NotificationDelivery::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('queued', $delivery->status->value);
        $this->assertSame('email', $delivery->channel->value);
        $this->assertSame($customer->email, $delivery->recipient_ref);
        $this->assertStringNotContainsString($customer->name, $delivery->message);
        $this->assertStringNotContainsString((string) $customer->normalized_phone, $delivery->message);
        Queue::assertPushed(SendNotificationDelivery::class, 1);

        Mail::fake();
        $delivery->refresh();
        (new SendNotificationDelivery($delivery->id, $tenant->id))->handle(
            app(TenantScope::class),
            app(NotificationSender::class),
        );

        $this->assertSame('sent', $delivery->refresh()->status->value);
        $this->assertNotNull($delivery->provider_reference);
    }

    public function test_kirimdev_whatsapp_adapter_uses_expected_payload_and_provider_reference(): void
    {
        config([
            'notifications.whatsapp_endpoint' => 'https://api.kirimdev.test/v1/123/messages',
            'notifications.whatsapp_token' => 'test-token',
        ]);
        Http::fake(['api.kirimdev.test/*' => Http::response(['messages' => [['id' => 'wamid-123']]], 200)]);
        $tenant = Tenant::factory()->create();
        $delivery = new NotificationDelivery([
            'event_id' => (string) Str::uuid(), 'channel' => NotificationChannel::Whatsapp,
            'business_key' => 'test:whatsapp', 'recipient_ref' => '081234567890',
            'subject' => 'Nota', 'message' => 'Pesanan siap', 'payload' => [],
            'status' => NotificationStatus::Queued,
        ]);
        $delivery->tenant_id = $tenant->id;
        $delivery->save();

        $result = app(NotificationSender::class)->send($delivery);

        $this->assertSame(NotificationStatus::Sent, $result->status);
        $this->assertSame('wamid-123', $result->providerReference);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.kirimdev.test/v1/123/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '+6281234567890'
            && $request['messaging_product'] === 'whatsapp'
            && $request['text']['body'] === 'Pesanan siap');
    }

    public function test_unknown_delivery_can_be_reconciled_by_platform_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        $token = app(IssueDeviceToken::class)->handle($admin, (string) Str::uuid(), 'Reconcile device')['token']->plainTextToken;
        $delivery = new NotificationDelivery([
            'event_id' => (string) Str::uuid(), 'channel' => NotificationChannel::Email,
            'business_key' => 'test:unknown', 'recipient_ref' => 'test@example.com',
            'subject' => 'Nota', 'message' => 'Pesan', 'payload' => [],
            'status' => NotificationStatus::Unknown,
        ]);
        $delivery->tenant_id = $tenant->id;
        $delivery->save();

        $this->withToken($token)
            ->postJson('/api/v1/platform/notifications/'.$delivery->public_id.'/reconcile', [
                'status' => 'sent',
                'provider_reference' => 'wamid-reconciled',
                'reason' => 'Provider dashboard menunjukkan delivered',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.provider_reference', 'wamid-reconciled');

        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'notification.reconciled']);
    }

    public function test_service_notice_is_not_created_without_service_consent(): void
    {
        [$tenant, $order, $customer] = $this->scenario();

        $event = app(OutboxWriter::class)->record(
            tenantId: $tenant->id,
            eventType: 'order.accepted',
            aggregatePublicId: $order->public_id,
            payload: ['order_id' => $order->public_id, 'status' => 'accepted'],
        );

        app(TenantScope::class)->forTenantId($tenant->id, 0, fn () => app(NotificationIntentConsumer::class)->handle($event));

        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    /** @return array{Tenant, Order, Customer} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $branch = Branch::factory()->for($tenant)->create(['name' => 'Outlet Uji', 'phone' => '081200000000']);
        $customer = new Customer(['name' => 'Nama Pelanggan', 'normalized_phone' => '081200000001', 'email' => 'pelanggan@example.com']);
        $customer->tenant_id = $tenant->id;
        $customer->save();
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id, 'customer_id' => $customer->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'sales_branch_id' => $branch->id,
            'operational_status' => 'accepted',
        ]);

        return [$tenant, $order, $customer];
    }
}
