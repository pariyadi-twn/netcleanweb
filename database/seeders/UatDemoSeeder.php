<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Actions\AcceptOrder;
use App\Modules\Order\Actions\CreateQuote;
use App\Modules\Order\Actions\NormalizeOrderInput;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\RecordPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class UatDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UAT demo data is disabled in production.');
        }

        Artisan::call('uat:provision', [
            '--tenant-name' => 'NetClean UAT',
            '--owner-email' => 'owner@uat.netclean.id',
        ]);

        $owner = User::query()->where('email', 'owner@uat.netclean.id')->firstOrFail();
        $tenantScope = app(TenantScope::class);
        $tenant = $tenantScope->identity(
            $owner,
            fn (): ?Tenant => Tenant::query()->where('name', 'NetClean UAT')->first(),
        );

        if ($tenant === null) {
            throw new RuntimeException('Tenant NetClean UAT tidak ditemukan setelah provisioning.');
        }

        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00:00', 'Asia/Jakarta'));

        try {
            $tenantScope->forTenantId($tenant->id, $owner->id, function () use ($owner, $tenant): void {
                $branch = Branch::query()->where('code', 'UAT-MAIN')->firstOrFail();
                $service = Service::query()->where('code', 'uat-kiloan')->firstOrFail();
                $cashier = User::query()->where('email', 'cashier@uat.netclean.id')->firstOrFail();
                $membership = TenantMembership::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $cashier->id)
                    ->firstOrFail();
                $context = app(MembershipAuthorizer::class)->contextFor($cashier, $tenant);
                if ($context === null) {
                    throw new RuntimeException('Konteks kasir UAT tidak tersedia.');
                }
                app()->instance(TenantContext::class, $context);

                try {
                    $this->createDemoOrder(
                        tenantId: $tenant->id,
                        owner: $owner,
                        branch: $branch,
                        service: $service,
                        membership: $membership,
                        cashier: $cashier,
                        name: 'Demo UAT - Belum Bayar',
                        phone: '081200001001',
                        paidRupiah: 0,
                    );
                    $this->createDemoOrder(
                        tenantId: $tenant->id,
                        owner: $owner,
                        branch: $branch,
                        service: $service,
                        membership: $membership,
                        cashier: $cashier,
                        name: 'Demo UAT - DP',
                        phone: '081200001002',
                        paidRupiah: 10000,
                    );
                    $this->createDemoOrder(
                        tenantId: $tenant->id,
                        owner: $owner,
                        branch: $branch,
                        service: $service,
                        membership: $membership,
                        cashier: $cashier,
                        name: 'Demo UAT - Lunas',
                        phone: '081200001003',
                        paidRupiah: 28000,
                    );
                } finally {
                    app()->forgetInstance(TenantContext::class);
                }
            });
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        $this->command?->info('Data demo UAT siap: 3 pelanggan/order contoh (belum bayar, DP, lunas).');
    }

    private function createDemoOrder(
        int $tenantId,
        User $owner,
        Branch $branch,
        Service $service,
        TenantMembership $membership,
        User $cashier,
        string $name,
        string $phone,
        int $paidRupiah,
    ): void {
        $customer = Customer::query()->where('name', $name)->first()
            ?? app(CreateCustomer::class)->handle($tenantId, [
                'name' => $name,
                'phone' => $phone,
            ], $owner);

        if (Order::query()->where('customer_id', $customer->id)->exists()) {
            return;
        }

        $input = [
            'branch_id' => $branch->public_id,
            'customer_id' => $customer->public_id,
            'fulfillment_method' => 'pickup',
            'instructions' => 'Data demo UAT',
            'lines' => [[
                'service_id' => $service->public_id,
                'measured_quantity' => '3.5',
                'tier' => 'regular',
            ]],
        ];

        $normalizer = app(NormalizeOrderInput::class);
        $normalized = $normalizer->handle($input);
        $quote = app(CreateQuote::class)->handle($tenantId, $input, $membership->id, true, true);
        $order = app(AcceptOrder::class)->handle($quote, $normalized, $cashier, $membership->id);

        if ($paidRupiah > 0) {
            $order->loadMissing('invoice');
            app(RecordPayment::class)->handle($order->invoice, [
                'method' => 'bank_manual',
                'amount_rupiah' => $paidRupiah,
            ], $membership->id, $cashier);
        }
    }
}
