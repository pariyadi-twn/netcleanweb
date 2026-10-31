<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class MyContextTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    public function test_only_active_memberships_of_the_authenticated_identity_are_returned(): void
    {
        $user = User::factory()->create();
        $activeTenant = Tenant::factory()->create();
        $membership = TenantMembership::factory()->for($activeTenant)->for($user)->create([
            'status' => 'active',
            'permission_version' => 4,
        ]);
        $branch = Branch::factory()->for($activeTenant)->create();
        $location = Location::factory()->atBranch($branch)->create();
        $membership->branches()->attach($branch->id, ['tenant_id' => $activeTenant->id]);
        $membership->locations()->attach($location->id, ['tenant_id' => $activeTenant->id]);

        $secondTenant = Tenant::factory()->create();
        $secondMembership = TenantMembership::factory()->for($secondTenant)->for($user)->create(['status' => 'active']);

        $suspendedTenant = Tenant::factory()->create(['status' => 'suspended']);
        TenantMembership::factory()->for($suspendedTenant)->for($user)->create(['status' => 'active']);
        TenantMembership::factory()->for($user)->create(['status' => 'disabled']);

        $otherUser = User::factory()->create();
        $otherMembership = TenantMembership::factory()->for($otherUser)->create(['status' => 'active']);

        $issued = $this->issueTokenFor($user);

        $response = $this->withToken($issued['token'])->getJson('/api/v1/me/context');

        $response->assertOk()->assertJsonCount(2, 'data');

        $contexts = collect($response->json('data'))->keyBy('tenant_id');

        $this->assertSame($membership->public_id, $contexts[$activeTenant->public_id]['membership_id']);
        $this->assertSame(4, $contexts[$activeTenant->public_id]['permission_version']);
        $this->assertSame([$branch->public_id], $contexts[$activeTenant->public_id]['branch_ids']);
        $this->assertSame([$location->public_id], $contexts[$activeTenant->public_id]['location_ids']);
        $this->assertSame($secondMembership->public_id, $contexts[$secondTenant->public_id]['membership_id']);

        $this->assertArrayNotHasKey($suspendedTenant->public_id, $contexts);
        $this->assertArrayNotHasKey($otherMembership->tenant->public_id, $contexts);
    }

    public function test_context_requires_authentication_and_an_active_account(): void
    {
        $this->getJson('/api/v1/me/context')->assertUnauthorized();

        $user = User::factory()->create();
        $issued = $this->issueTokenFor($user);
        $user->forceFill(['status' => 'disabled'])->save();

        $this->withToken($issued['token'])->getJson('/api/v1/me/context')->assertUnauthorized();
    }
}
