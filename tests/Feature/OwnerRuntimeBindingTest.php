<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerRuntimeBindingTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_owner_detail_pages_resolve_tenant_models_after_runtime_context(): void
    {
        config(['identity.mfa.mode' => 'disabled']);

        $this->artisan('uat:provision')->assertSuccessful();

        $owner = User::query()->where('email', 'owner@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'UAT-MAIN')->sole();
        $role = Role::query()->where('tenant_id', $tenant->id)->where('code', 'owner')->sole();
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->sole();

        $previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_runtime');

        try {
            $this->actingAs($owner)
                ->withSession(['tenant.public_id' => $tenant->public_id])
                ->get('/branches/'.$branch->public_id)
                ->assertOk()
                ->assertSee('Kembali')
                ->assertDontSee('name="timezone"', false);

            $this->actingAs($owner)
                ->withSession(['tenant.public_id' => $tenant->public_id])
                ->get('/roles/'.$role->public_id)
                ->assertOk();

            $this->actingAs($owner)
                ->withSession(['tenant.public_id' => $tenant->public_id])
                ->get('/staff/'.$membership->public_id)
                ->assertOk();
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }
}
