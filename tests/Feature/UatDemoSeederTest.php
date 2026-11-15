<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Database\Seeders\UatDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UatDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_creates_idempotent_demo_customers_and_orders(): void
    {
        $this->seed(UatDemoSeeder::class);

        $this->assertSame(
            ['Demo UAT - Belum Bayar', 'Demo UAT - DP', 'Demo UAT - Lunas'],
            Customer::query()->where('name', 'like', 'Demo UAT%')->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(2, Payment::query()->count());

        $this->seed(UatDemoSeeder::class);

        $this->assertSame(3, Customer::query()->where('name', 'like', 'Demo UAT%')->count());
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(2, Payment::query()->count());
    }
}
