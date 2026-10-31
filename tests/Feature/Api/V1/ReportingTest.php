<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\InvoiceAdjustment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\RefundAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outlet_report_uses_local_dates_and_reconciles_daily_detail_with_totals(): void
    {
        [$tenant, $token, $branch] = $this->scenario();

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/outlet?branch_id='.$branch->public_id.'&from=2026-10-31&to=2026-11-01')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.period.from', '2026-10-31')
            ->assertJsonPath('data.period.to', '2026-11-01')
            ->assertJsonPath('data.date_basis.sales', 'invoice.issued_at')
            ->assertJsonPath('data.totals.invoice_original_rupiah', 121600)
            ->assertJsonPath('data.totals.adjustment_rupiah', -2000)
            ->assertJsonPath('data.totals.sales_net_rupiah', 119600)
            ->assertJsonPath('data.totals.invoice_count', 2)
            ->assertJsonPath('data.totals.adjustment_count', 1)
            ->assertJsonPath('data.totals.receipts_rupiah', 40000)
            ->assertJsonPath('data.totals.cash_receipts_rupiah', 30000)
            ->assertJsonPath('data.totals.bank_receipts_rupiah', 10000)
            ->assertJsonPath('data.totals.payment_count', 2)
            ->assertJsonPath('data.totals.refund_rupiah', 10000)
            ->assertJsonPath('data.totals.refund_count', 1)
            ->assertJsonPath('data.totals.receivables_rupiah', 89600)
            ->assertJsonPath('data.totals.receivable_invoice_count', 2)
            ->assertJsonPath('data.totals.pending_orders_current', 2)
            ->assertJsonPath('data.daily.0.business_date', '2026-10-31')
            ->assertJsonPath('data.daily.0.invoice_original_rupiah', 71600)
            ->assertJsonPath('data.daily.0.sales_net_rupiah', 71600)
            ->assertJsonPath('data.daily.0.receipts_rupiah', 30000)
            ->assertJsonPath('data.daily.0.cash_receipts_rupiah', 30000)
            ->assertJsonPath('data.daily.1.business_date', '2026-11-01')
            ->assertJsonPath('data.daily.1.invoice_original_rupiah', 50000)
            ->assertJsonPath('data.daily.1.adjustment_rupiah', -2000)
            ->assertJsonPath('data.daily.1.sales_net_rupiah', 48000)
            ->assertJsonPath('data.daily.1.bank_receipts_rupiah', 10000)
            ->assertJsonPath('data.daily.1.refund_rupiah', 10000);

        $this->assertNotNull($response->json('data.watermark'));
        $this->assertNull($response->json('data.totals.on_time_completion.rate'));
        $this->assertFalse($response->json('data.totals.on_time_completion.available'));

        $daily = collect($response->json('data.daily'));
        $this->assertSame($response->json('data.totals.invoice_original_rupiah'), $daily->sum('invoice_original_rupiah'));
        $this->assertSame($response->json('data.totals.receipts_rupiah'), $daily->sum('receipts_rupiah'));
        $this->assertSame($response->json('data.totals.refund_rupiah'), $daily->sum('refund_rupiah'));
    }

    public function test_receivables_report_lists_only_outstanding_invoices_and_uses_branch_scope(): void
    {
        [$tenant, $token, $branch] = $this->scenario();

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/receivables?branch_id='.$branch->public_id.'&as_of=2026-11-01')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.as_of', '2026-11-01')
            ->assertJsonPath('summary.invoice_count', 2)
            ->assertJsonPath('summary.receivables_rupiah', 89600)
            ->assertJsonPath('summary.overdue_invoice_count', 0);

        $this->assertSame(89600, collect($response->json('data'))->sum('balance_due_rupiah'));
        $this->assertSame(119600, collect($response->json('data'))->sum('total_rupiah'));
        $this->assertSame(['Ayu', 'Budi'], collect($response->json('data'))->pluck('customer.name')->sort()->values()->all());

        $otherTenant = Tenant::factory()->create();
        $otherBranch = Branch::factory()->for($otherTenant)->create();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/receivables?branch_id='.$otherBranch->public_id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/outlet?branch_id='.$otherBranch->public_id.'&from=2026-11-01&to=2026-11-01')
            ->assertNotFound();
    }

    public function test_report_period_validation_and_permission_are_enforced(): void
    {
        [$tenant, $token, $branch, $role, $user] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/outlet?branch_id='.$branch->public_id.'&from=2026-11-02&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['to']]]);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/outlet?branch_id='.$branch->public_id.'&from=2026-01-01&to=2026-12-31')
            ->assertUnprocessable();

        app(SyncRolePermissions::class)->handle(
            $role,
            [Permission::query()->firstOrCreate(['code' => PermissionCode::OrdersView->value], ['description' => PermissionCode::OrdersView->name])->id],
            $user,
            'Remove report permission',
        );

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/reports/receivables?branch_id='.$branch->public_id)
            ->assertForbidden();
    }

    /** @return array{Tenant, string, Branch, Role, User} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'owner', 'name' => 'Owner']);
        $branch = Branch::factory()->for($tenant)->create(['timezone' => 'Asia/Jakarta']);
        $permissions = collect([PermissionCode::ReportsView])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [], $user, 'Bootstrap report owner');

        $quoteA = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $orderA = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quoteA->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'ORD-A',
            'operational_status' => 'accepted',
            'customer_snapshot' => ['name' => 'Ayu', 'normalized_phone' => '081200000001'],
        ]);
        $invoiceA = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $orderA->id,
            'sales_branch_id' => $branch->id,
            'original_total_rupiah' => 71600,
            'issued_at' => CarbonImmutable::parse('2026-10-31 03:00:00', 'UTC'),
            'due_at' => CarbonImmutable::parse('2026-11-10 17:00:00', 'UTC'),
        ]);

        $quoteB = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $orderB = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quoteB->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'order_number' => 'ORD-B',
            'operational_status' => 'ready',
            'customer_snapshot' => ['name' => 'Budi', 'normalized_phone' => '081200000002'],
        ]);
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $orderB->id,
            'sales_branch_id' => $branch->id,
            'original_total_rupiah' => 50000,
            'issued_at' => CarbonImmutable::parse('2026-10-31 17:30:00', 'UTC'),
            'due_at' => CarbonImmutable::parse('2026-11-10 17:00:00', 'UTC'),
        ]);

        $adjustment = new InvoiceAdjustment([
            'invoice_id' => $invoiceA->id,
            'kind' => 'credit_note',
            'amount_rupiah' => -2000,
            'reason' => 'Kompensasi',
            'requested_by_membership_id' => $membership->id,
            'approved_by_membership_id' => $membership->id,
        ]);
        $adjustment->tenant_id = $tenant->id;
        $adjustment->created_at = CarbonImmutable::parse('2026-10-31 18:00:00', 'UTC');
        $adjustment->save();

        $paymentA = $this->payment($tenant, $branch, $membership, 30000, 'cash', CarbonImmutable::parse('2026-10-31 05:00:00', 'UTC'));
        $allocationA = $this->allocation($tenant, $paymentA, $invoiceA, 30000);
        $paymentB = $this->payment($tenant, $branch, $membership, 10000, 'bank_manual', CarbonImmutable::parse('2026-11-01 01:00:00', 'UTC'));
        $this->allocation($tenant, $paymentB, $invoiceB, 10000);

        $refund = new Refund([
            'payment_id' => $paymentA->id,
            'amount_rupiah' => 10000,
            'status' => RefundStatus::Succeeded,
            'requested_by_membership_id' => $membership->id,
            'approved_by_membership_id' => $membership->id,
            'reason' => 'Kelebihan bayar',
            'succeeded_at' => CarbonImmutable::parse('2026-11-01 02:00:00', 'UTC'),
        ]);
        $refund->tenant_id = $tenant->id;
        $refund->save();
        $refundAllocation = new RefundAllocation([
            'refund_id' => $refund->id,
            'payment_id' => $paymentA->id,
            'payment_allocation_id' => $allocationA->id,
            'amount_rupiah' => 10000,
        ]);
        $refundAllocation->tenant_id = $tenant->id;
        $refundAllocation->save();

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Report device')['token']->plainTextToken;

        return [$tenant, $token, $branch, $role, $user];
    }

    private function payment(Tenant $tenant, Branch $branch, TenantMembership $membership, int $amount, string $method, CarbonImmutable $receivedAt): Payment
    {
        $payment = new Payment([
            'received_branch_id' => $branch->id,
            'method' => $method,
            'amount_rupiah' => $amount,
            'currency' => 'IDR',
            'cash_received_rupiah' => $amount,
            'change_rupiah' => 0,
            'unallocated_rupiah' => 0,
            'received_at' => $receivedAt,
            'received_by_membership_id' => $membership->id,
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();

        return $payment;
    }

    private function allocation(Tenant $tenant, Payment $payment, Invoice $invoice, int $amount): PaymentAllocation
    {
        $allocation = new PaymentAllocation([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_rupiah' => $amount,
        ]);
        $allocation->tenant_id = $tenant->id;
        $allocation->save();

        return $allocation;
    }
}
