<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatCashierFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uat_catalog_supports_the_mobile_cashier_flow(): void
    {
        $this->artisan('uat:provision')->assertSuccessful();

        $tenant = Tenant::query()->sole();
        $cashier = User::query()->where('email', 'cashier@uat.netclean.id')->sole();
        $branch = Branch::query()->where('code', 'UAT-MAIN')->sole();
        $service = Service::query()->where('code', 'uat-kiloan')->sole();
        $customer = Customer::query()->where('name', 'Pelanggan UAT')->sole();
        $token = app(IssueDeviceToken::class)
            ->handle($cashier, (string) Str::uuid(), 'UAT cashier device')['token']
            ->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id);

        $this->getJson('/api/v1/branches/'.$branch->public_id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Cabang UAT Utama');

        $this->getJson('/api/v1/services?active=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $service->public_id, 'code' => 'uat-kiloan']);

        $this->getJson('/api/v1/customers?q=Pelanggan')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->public_id, 'name' => 'Pelanggan UAT']);

        $quote = $this->postJson('/api/v1/orders/quotes', [
            'branch_id' => $branch->public_id,
            'customer_id' => $customer->public_id,
            'fulfillment_method' => 'pickup',
            'lines' => [
                ['service_id' => $service->public_id, 'measured_quantity' => '3.5'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_rupiah', 28000)
            ->json('data');

        $orderCommand = (string) Str::uuid();
        $order = $this->withHeader('Idempotency-Key', $orderCommand)
            ->postJson('/api/v1/orders', [
                'client_command_id' => $orderCommand,
                'quote_id' => $quote['id'],
                'branch_id' => $branch->public_id,
                'customer_id' => $customer->public_id,
                'fulfillment_method' => 'pickup',
                'lines' => [
                    ['service_id' => $service->public_id, 'measured_quantity' => '3.5'],
                ],
            ])->assertCreated()
            ->assertJsonPath('data.total_rupiah', 28000)
            ->assertJsonPath('data.balance_due_rupiah', 28000)
            ->json('data');

        $paymentCommand = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $paymentCommand)
            ->postJson('/api/v1/invoices/'.$order['invoice_id'].'/payments', [
                'client_command_id' => $paymentCommand,
                'amount_rupiah' => 10000,
                'method' => 'bank_manual',
            ])->assertCreated()
            ->assertJsonPath('data.invoice_status', 'partially_paid')
            ->assertJsonPath('data.balance_due_rupiah', 18000);

        $this->postJson('/api/v1/orders/'.$order['order_id'].'/receipt')
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Pelanggan UAT')
            ->assertJsonPath('data.totals.total_rupiah', 28000)
            ->assertJsonPath('data.totals.balance_due_rupiah', 18000)
            ->assertJsonPath('data.lines.0.service_name', 'Kiloan UAT');
    }
}
