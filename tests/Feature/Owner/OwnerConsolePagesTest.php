<?php

namespace Tests\Feature\Owner;

use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Models\ProductionTask;
use Database\Seeders\UatDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OwnerConsolePagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['identity.mfa.mode' => 'disabled']);
        $this->artisan('uat:provision')->assertSuccessful();
    }

    public function test_owner_can_open_all_console_pages(): void
    {
        $owner = User::query()->where('email', 'owner@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'UAT-MAIN')->sole();
        $session = ['tenant.public_id' => $tenant->public_id];

        foreach (['/dashboard', '/settings', '/orders', '/production', '/finance', '/finance/cash', '/finance/payments', '/finance/expenses', '/finance/refunds', '/reports', '/reports/imports'] as $path) {
            $this->actingAs($owner)->withSession($session)->get($path)->assertOk();
        }

        $this->actingAs($owner)->withSession($session)->get('/reports/outlet?'.http_build_query([
            'branch_id' => $branch->public_id,
            'from' => now()->subDays(6)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        $this->actingAs($owner)->withSession($session)->get('/reports/receivables?'.http_build_query([
            'branch_id' => $branch->public_id,
            'as_of' => now()->toDateString(),
        ]))->assertOk();
    }

    public function test_owner_can_open_seeded_order_and_receipt_pages(): void
    {
        $this->artisan('db:seed', ['--class' => UatDemoSeeder::class])->assertSuccessful();

        $owner = User::query()->where('email', 'owner@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();
        $order = Order::query()->where('tenant_id', $tenant->id)->where('order_number', 'UAT-MAIN-20261116-000001')->sole();

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/orders')
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/orders/'.$order->public_id)
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/orders/'.$order->public_id.'/receipt')
            ->assertOk()
            ->assertSee($order->order_number);

        $location = Location::query()->where('tenant_id', $tenant->id)->where('code', 'PLANT-MAIN')->sole();
        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->post('/orders/'.$order->public_id.'/production-tasks', ['location_id' => $location->public_id])
            ->assertRedirect();

        $task = ProductionTask::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/production/tasks/'.$task->public_id)
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_owner_navigation_contains_operation_and_settings_links(): void
    {
        $owner = User::query()->where('email', 'owner@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();

        $this->actingAs($owner)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pesanan')
            ->assertSee('Produksi')
            ->assertSee('Keuangan')
            ->assertSee('Laporan')
            ->assertSee('Pengaturan')
            ->assertDontSee('cursor-not-allowed', false);
    }

    public function test_cashier_cannot_open_owner_console(): void
    {
        $cashier = User::query()->where('email', 'cashier@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();

        $this->actingAs($cashier)
            ->withSession(['tenant.public_id' => $tenant->public_id])
            ->get('/orders')
            ->assertForbidden();
    }
}
