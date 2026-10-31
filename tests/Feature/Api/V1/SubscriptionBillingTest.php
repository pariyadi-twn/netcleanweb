<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\SubscriptionPayment;
use App\Modules\Saas\Models\TenantSubscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_payment_marks_invoice_paid_and_activates_subscription_once_without_cash(): void
    {
        [$tenant, $token, $subscription, $platform] = $this->scenario();
        $periodStart = now()->startOfMonth()->toIso8601String();
        $periodEnd = now()->endOfMonth()->toIso8601String();

        $invoice = $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/'.$tenant->public_id.'/subscription-invoices', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.amount_rupiah', 150000)
            ->assertJsonPath('data.tenant_id', $tenant->public_id);

        $invoiceId = $invoice->json('data.invoice_id');

        $payment = $this->withToken($token)
            ->postJson('/api/v1/platform/subscription-invoices/'.$invoiceId.'/payments', [
                'amount_rupiah' => 150000,
                'reference' => 'MANUAL-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.reference', 'MANUAL-001')
            ->assertJsonPath('data.amount_rupiah', 150000);

        $this->assertSame($invoiceId, $payment->json('data.invoice_id'));
        $this->assertDatabaseHas('saas_subscription_payments', ['tenant_id' => $tenant->id, 'reference' => 'MANUAL-001']);
        $this->assertDatabaseHas('saas_subscription_invoices', ['tenant_id' => $tenant->id, 'public_id' => $invoiceId, 'status' => 'paid']);
        $this->assertSame(0, DB::table('cash_entries')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('payments')->where('tenant_id', $tenant->id)->count());

        $this->withToken($token)
            ->postJson('/api/v1/platform/subscription-invoices/'.$invoiceId.'/payments', [
                'amount_rupiah' => 150000,
                'reference' => 'MANUAL-001',
            ])
            ->assertOk();

        $this->assertSame(1, SubscriptionPayment::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, SubscriptionInvoice::query()->where('tenant_id', $tenant->id)->count());

        $subscription->refresh();
        $this->assertSame('active', $subscription->status->value);
        $this->assertSame(now()->startOfMonth()->toDateString(), $subscription->current_period_start->toDateString());

        $nextInvoice = $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/'.$tenant->public_id.'/subscription-invoices', [
                'period_start' => now()->addMonth()->startOfMonth()->toIso8601String(),
                'period_end' => now()->addMonth()->endOfMonth()->toIso8601String(),
            ])
            ->assertCreated()
            ->json('data.invoice_id');

        $this->withToken($token)
            ->postJson('/api/v1/platform/subscription-invoices/'.$nextInvoice.'/payments', [
                'amount_rupiah' => 100,
                'reference' => 'MANUAL-002',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['amount_rupiah']]]);
    }

    public function test_non_platform_admin_cannot_manage_subscription_billing(): void
    {
        [$tenant, $token] = $this->scenario(platform: false);

        $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/'.$tenant->public_id.'/subscription-invoices', [
                'period_start' => now()->startOfMonth()->toIso8601String(),
                'period_end' => now()->endOfMonth()->toIso8601String(),
            ])
            ->assertForbidden();
    }

    /** @return array{Tenant, string, TenantSubscription, User} */
    private function scenario(bool $platform = true): array
    {
        $tenant = Tenant::factory()->create();
        $plan = SaasPlan::factory()->create([
            'code' => 'pilot',
            'revision' => 1,
            'base_price_rupiah' => 150000,
            'features' => ['limits' => ['staff' => 5], 'features' => ['exports' => true]],
        ]);
        $subscription = app(StartSubscription::class)->handle($tenant, $plan);

        $user = $platform ? User::factory()->platformAdmin()->create() : User::factory()->create();
        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Platform billing device')['token']->plainTextToken;

        return [$tenant, $token, $subscription, $user];
    }
}
