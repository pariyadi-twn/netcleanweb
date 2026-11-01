<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Bag;
use App\Modules\Order\Models\BagContent;
use App\Modules\Order\Models\Invoice;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderLine;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Models\CreditApproval;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Production\Models\ProductionTask;
use App\Modules\Production\Models\QcResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_whole_bag_handover_is_partial_then_completed_and_cannot_repeat(): void
    {
        [$tenant, $token, $order, $location, $bags, $membership] = $this->scenario(100000);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[0]->public_id],
                'location_id' => $location->public_id,
                'recipient_name' => 'Siti Penerima',
                'verification_method' => 'receipt',
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.operational_status', 'partially_handed_over')
            ->assertJsonPath('data.handover.recipient_name', 'Siti Penerima')
            ->assertJsonCount(1, 'data.handover.lines');

        $this->assertDatabaseHas('bags', ['id' => $bags[0]->id, 'custody_status' => 'handed_over', 'current_location_id' => null]);
        $this->assertDatabaseHas('custody_events', ['tenant_id' => $tenant->id, 'bag_id' => $bags[0]->id, 'event_type' => 'handed_over']);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[0]->public_id],
                'location_id' => $location->public_id,
                'recipient_name' => 'Siti Penerima',
                'verification_method' => 'receipt',
            ])
            ->assertStatus(409);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[1]->public_id],
                'location_id' => $location->public_id,
                'recipient_name' => 'Siti Penerima',
                'verification_method' => 'receipt',
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.operational_status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'operational_status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'handover.completed']);
    }

    public function test_unpaid_order_requires_active_credit_approval_and_location_is_scoped(): void
    {
        [$tenant, $token, $order, $location, $bags, $membership] = $this->scenario(100000, paid: false);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[0]->public_id],
                'location_id' => $location->public_id,
                'recipient_name' => 'Penerima',
                'verification_method' => 'otp',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENT_OR_CREDIT_REQUIRED');

        $approval = new CreditApproval([
            'invoice_id' => $order->invoice->id,
            'approved_by_membership_id' => $membership->id,
            'limit_rupiah' => 100000,
            'expires_at' => now()->addDay(),
            'reason' => 'Langganan',
            'status' => 'active',
        ]);
        $approval->tenant_id = $tenant->id;
        $approval->save();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[0]->public_id],
                'location_id' => $location->public_id,
                'recipient_name' => 'Penerima',
                'verification_method' => 'otp',
                'credit_approval_id' => $approval->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.handover.credit_approval_id', $approval->public_id);

        $otherLocation = Location::factory()->for($tenant)->create();
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->postJson('/api/v1/orders/'.$order->public_id.'/handovers', [
                'bag_ids' => [$bags[1]->public_id],
                'location_id' => $otherLocation->public_id,
                'recipient_name' => 'Penerima',
                'verification_method' => 'receipt',
            ])
            ->assertForbidden();
    }

    /** @return array{Tenant, string, Order, Location, array<int, Bag>, TenantMembership} */
    private function scenario(int $total, bool $paid = true): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);
        $role = Role::factory()->for($tenant)->create(['code' => 'cashier', 'name' => 'Cashier']);
        $branch = Branch::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'outlet']);
        $otherLocation = Location::factory()->for($tenant)->create(['branch_id' => $branch->id, 'type' => 'outlet']);
        $permissions = collect([PermissionCode::HandoversManage, PermissionCode::ProductionView, PermissionCode::ProductionQc])
            ->map(fn (PermissionCode $code): int => Permission::query()->firstOrCreate(['code' => $code->value], ['description' => $code->name])->id);
        DB::table('role_permissions')->insert($permissions->map(fn (int $id): array => [
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $id,
        ])->all());
        app(UpdateMembershipAssignments::class)->handle($membership, [$role->id], [$branch->id], [$location->id, $otherLocation->id], $user, 'Bootstrap handover');

        $workflow = WorkflowTemplate::factory()->for($tenant)->create(['code' => 'standard', 'is_active' => true]);
        $service = Service::factory()->forWorkflow($workflow)->create(['code' => 'kiloan', 'name' => 'Cuci Kiloan']);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'sales_branch_id' => $branch->id]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'sales_branch_id' => $branch->id,
            'accepted_by_membership_id' => $membership->id,
            'operational_status' => 'ready',
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'sales_branch_id' => $branch->id,
            'original_total_rupiah' => $total,
        ]);
        $line = OrderLine::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'service_id' => $service->id,
            'service_snapshot' => ['name' => 'Cuci Kiloan', 'workflow' => ['steps' => [['code' => 'qc', 'sequence' => 1, 'is_required' => true, 'requires_qc' => true]]]],
        ]);

        $bags = [];
        foreach ([1, 2] as $index) {
            $bag = Bag::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'current_location_id' => $location->id]);
            $content = BagContent::factory()->create([
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'bag_id' => $bag->id,
                'order_line_id' => $line->id,
            ]);
            $task = new ProductionTask([
                'bag_content_id' => $content->id,
                'production_location_id' => $location->id,
                'step_code' => 'qc',
                'sequence' => 1,
                'is_required' => true,
                'requires_qc' => true,
                'attempt_no' => 1,
                'status' => 'done',
                'due_at' => now()->addDay(),
                'completed_at' => now(),
            ]);
            $task->tenant_id = $tenant->id;
            $task->save();
            $qc = new QcResult([
                'bag_content_id' => $content->id,
                'production_task_id' => $task->id,
                'inspector_membership_id' => $membership->id,
                'outcome' => 'passed',
                'checklist' => ['clean' => true],
                'attempt_no' => 1,
            ]);
            $qc->tenant_id = $tenant->id;
            $qc->save();
            $bags[] = $bag;
        }

        if ($paid) {
            $payment = new Payment([
                'received_branch_id' => $branch->id,
                'method' => 'bank_manual',
                'amount_rupiah' => $total,
                'currency' => 'IDR',
                'cash_received_rupiah' => $total,
                'change_rupiah' => 0,
                'unallocated_rupiah' => 0,
                'received_at' => now(),
                'received_by_membership_id' => $membership->id,
            ]);
            $payment->tenant_id = $tenant->id;
            $payment->save();
            $allocation = new PaymentAllocation(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_rupiah' => $total]);
            $allocation->tenant_id = $tenant->id;
            $allocation->save();
        }

        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Handover device')['token']->plainTextToken;

        return [$tenant, $token, $order, $location, $bags, $membership];
    }
}
