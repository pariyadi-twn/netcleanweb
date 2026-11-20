<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Reporting\Models\ReportExport;
use App\Policies\ReportExportPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantBoundaryMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_report_export_policy_rejects_manipulated_branch_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $assignedBranch = Branch::factory()->for($tenant)->create();
        $otherBranch = Branch::factory()->for($tenant)->create();
        $membership = TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);

        $export = new ReportExport([
            'requested_by_membership_id' => $membership->id,
            'requested_by_user_id' => $user->id,
            'report_type' => 'outlet_sales',
            'filters' => ['branch_id' => $otherBranch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-01'],
            'expires_at' => now()->addDay(),
        ]);
        $export->tenant_id = $tenant->id;
        $export->save();

        $assigned = new ReportExportPolicy($this->context($tenant, $user, [$assignedBranch->id]));
        $this->assertFalse($assigned->view($user, $export));
        $this->assertFalse($assigned->download($user, $export));

        $other = new ReportExportPolicy($this->context($tenant, $user, [$otherBranch->id]));
        $this->assertTrue($other->view($user, $export));
        $this->assertTrue($other->download($user, $export));
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function context(Tenant $tenant, User $user, array $branchIds): TenantContext
    {
        return new TenantContext(
            userId: $user->id,
            tenantId: $tenant->id,
            tenantPublicId: $tenant->public_id,
            membershipId: 1,
            membershipPublicId: (string) fake()->uuid(),
            permissionVersion: 1,
            branchIds: $branchIds,
            locationIds: [],
            permissions: [PermissionCode::ReportsExport->value],
        );
    }
}
