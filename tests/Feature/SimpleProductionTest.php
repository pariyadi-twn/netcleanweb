<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimpleProductionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_simple_mode_auto_plans_and_completes_production_and_allows_payment_after_pickup(): void
    {
        $this->post('/register', $this->registrationPayload())->assertRedirect(route('tenants.select'));

        $tenant = Tenant::query()->where('name', 'Laundry Baru')->sole();
        $owner = User::query()->where('email', 'pemilik@example.test')->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->sole();
        $location = Location::query()->where('tenant_id', $tenant->id)->where('code', 'OUTLET')->sole();
        $token = app(IssueDeviceToken::class)
            ->handle($owner, (string) Str::uuid(), 'Simple production device')['token']
            ->plainTextToken;

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant->public_id);
        $services = $this->getJson('/api/v1/services?active=1')
            ->assertOk()
            ->json('data');
        $serviceId = collect($services)->firstWhere('code', 'kiloan')['id'];

        $quote = $this->postJson('/api/v1/orders/quotes', [
            'branch_id' => $branch->public_id,
            'fulfillment_method' => 'pickup',
            'lines' => [
                ['service_id' => $serviceId, 'measured_quantity' => '3.5'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_rupiah', 28000)
            ->json('data');

        $command = (string) Str::uuid();
        $order = $this->withHeader('Idempotency-Key', $command)
            ->postJson('/api/v1/orders', [
                'client_command_id' => $command,
                'quote_id' => $quote['id'],
                'branch_id' => $branch->public_id,
                'fulfillment_method' => 'pickup',
                'lines' => [
                    ['service_id' => $serviceId, 'measured_quantity' => '3.5'],
                ],
            ])->assertCreated()
            ->assertJsonPath('data.balance_due_rupiah', 28000)
            ->json('data');

        $orderModel = Order::query()->where('public_id', $order['order_id'])->sole();
        $this->assertSame(3, ProductionTask::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('content', fn ($query) => $query->where('order_id', $orderModel->id))
            ->count());

        $this->getJson('/api/v1/orders/'.$order['order_id'].'/production-summary')
            ->assertOk()
            ->assertJsonPath('data.stage', 'processing')
            ->assertJsonPath('data.next_action', 'complete')
            ->assertJsonPath('data.balance_due_rupiah', 28000)
            ->assertJsonPath('data.can_pay_later', true);

        $this->postJson('/api/v1/orders/'.$order['order_id'].'/production-complete')
            ->assertOk()
            ->assertJsonPath('data.can_ready', true);

        $this->assertSame('ready', $orderModel->refresh()->operational_status->value);
        $this->assertSame(3, ProductionTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'done')
            ->count());

        $bagId = $this->getJson('/api/v1/orders?order_number='.urlencode($order['order_number']))
            ->assertOk()
            ->json('data.0.bags.0.id');

        $this->postJson('/api/v1/orders/'.$order['order_id'].'/bags/'.$bagId.'/receive', [
            'location_id' => $location->public_id,
            'expected_version' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/orders/'.$order['order_id'].'/handovers', [
            'bag_ids' => [$bagId],
            'location_id' => $location->public_id,
            'recipient_name' => 'Pelanggan',
            'verification_method' => 'receipt',
        ])->assertCreated()
            ->assertJsonPath('data.order.operational_status', 'completed')
            ->assertJsonPath('data.handover.credit_approval_id', null);

        $this->assertSame('completed', $orderModel->refresh()->operational_status->value);
        $this->assertSame(28000, $orderModel->invoice->totalRupiah() - $orderModel->invoice->netAllocatedRupiah());

        $paymentCommand = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $paymentCommand)
            ->postJson('/api/v1/invoices/'.$order['invoice_id'].'/payments', [
                'client_command_id' => $paymentCommand,
                'amount_rupiah' => 28000,
                'method' => 'bank_manual',
            ])->assertCreated()
            ->assertJsonPath('data.invoice_status', 'paid')
            ->assertJsonPath('data.balance_due_rupiah', 0);
    }

    /**
     * @return array<string, string>
     */
    private function registrationPayload(): array
    {
        return [
            'name' => 'Pemilik Baru',
            'email' => 'pemilik@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'organization_name' => 'Laundry Baru',
            'branch_name' => 'Cabang Utama',
            'branch_address' => 'Jl. Baru No. 1',
            'branch_phone' => '081234567892',
            'terms' => '1',
        ];
    }
}
