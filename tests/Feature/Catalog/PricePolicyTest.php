<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Tenant;
use App\Policies\PriceBookPolicy;
use App\Policies\PriceEntryPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PricePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_price_policies_require_the_catalog_permission_and_matching_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $workflow = WorkflowTemplate::factory()->for($tenant)->create();
        $service = Service::factory()->forWorkflow($workflow)->create();
        $book = PriceBook::factory()->for($tenant)->create();
        $entry = PriceEntry::factory()->forPriceBook($book, $service)->create();
        $otherBook = PriceBook::factory()->create();
        $otherWorkflow = WorkflowTemplate::factory()->for($otherBook->tenant)->create();
        $otherService = Service::factory()->forWorkflow($otherWorkflow)->create();
        $otherEntry = PriceEntry::factory()->forPriceBook($otherBook, $otherService)->create();

        $viewerContext = $this->context($tenant, $user, [PermissionCode::CatalogView]);
        $viewerBooks = new PriceBookPolicy($viewerContext);
        $viewerEntries = new PriceEntryPolicy($viewerContext);

        $this->assertTrue($viewerBooks->viewAny($user));
        $this->assertTrue($viewerBooks->view($user, $book));
        $this->assertFalse($viewerBooks->view($user, $otherBook));
        $this->assertFalse($viewerBooks->create($user));
        $this->assertFalse($viewerBooks->update($user, $book));
        $this->assertTrue($viewerEntries->view($user, $entry));
        $this->assertFalse($viewerEntries->view($user, $otherEntry));
        $this->assertFalse($viewerEntries->update($user, $entry));

        $managerContext = $this->context($tenant, $user, [PermissionCode::CatalogManage]);
        $managerBooks = new PriceBookPolicy($managerContext);
        $managerEntries = new PriceEntryPolicy($managerContext);

        $this->assertFalse($managerBooks->viewAny($user));
        $this->assertTrue($managerBooks->create($user));
        $this->assertTrue($managerBooks->update($user, $book));
        $this->assertFalse($managerBooks->update($user, $otherBook));
        $this->assertTrue($managerEntries->update($user, $entry));
        $this->assertFalse($managerEntries->update($user, $otherEntry));
    }

    /**
     * @param  list<PermissionCode>  $permissions
     */
    private function context(Tenant $tenant, User $user, array $permissions): TenantContext
    {
        return new TenantContext(
            userId: $user->id,
            tenantId: $tenant->id,
            tenantPublicId: $tenant->public_id,
            membershipId: 1,
            membershipPublicId: (string) fake()->uuid(),
            permissionVersion: 1,
            branchIds: [],
            locationIds: [],
            permissions: array_map(fn (PermissionCode $permission): string => $permission->value, $permissions),
        );
    }
}
