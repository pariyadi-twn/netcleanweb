<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Saas\Actions\ConsumeQuota;
use App\Modules\Saas\Actions\StartSubscription;
use App\Modules\Saas\Enums\SubscriptionStatus;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\TenantSubscription;
use App\Modules\Saas\Models\TenantUsageCounter;
use App\Modules\Saas\SubscriptionEntitlements;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionQuotaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_subscription_entitlements_are_visible_to_the_server_and_client(): void
    {
        [$tenant, $plan, $subscription, $token] = $this->scenario();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription_id', $subscription->public_id)
            ->assertJsonPath('data.plan_code', $plan->code)
            ->assertJsonPath('data.plan_revision', 1)
            ->assertJsonPath('data.limits.staff', 1)
            ->assertJsonPath('data.features.exports', false);

        $entitlements = app(SubscriptionEntitlements::class);
        $this->assertSame(1, $entitlements->limit($tenant->id, 'staff'));
        $this->assertFalse($entitlements->allows($tenant->id, 'exports'));
    }

    public function test_last_quota_slot_is_granted_only_once_and_release_restores_it(): void
    {
        [$tenant] = $this->scenario();
        $quota = app(ConsumeQuota::class);

        $this->assertSame(1, $quota->handle($tenant->id, 'staff'));

        try {
            $quota->handle($tenant->id, 'staff');
            $this->fail('The last staff slot must not be granted twice.');
        } catch (ApiException $exception) {
            $this->assertSame('ENTITLEMENT_LIMIT_REACHED', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }

        $this->assertSame(1, TenantUsageCounter::query()->sole()->used);
        $this->assertSame(0, TenantUsageCounter::query()->sole()->reserved);

        $quota->release($tenant->id, 'staff');
        $this->assertSame(0, TenantUsageCounter::query()->sole()->used);
        $this->assertSame(1, $quota->handle($tenant->id, 'staff'));
    }

    public function test_paid_feature_requires_a_true_subscription_snapshot(): void
    {
        [$tenant, $plan] = $this->scenario();

        try {
            app(SubscriptionEntitlements::class)->requireFeature($tenant->id, 'exports');
            $this->fail('A disabled paid feature must be rejected by the server.');
        } catch (ApiException $exception) {
            $this->assertSame('FEATURE_NOT_AVAILABLE', $exception->errorCode);
            $this->assertSame(403, $exception->status);
        }

        $plan->update([
            'features' => [
                'limits' => ['staff' => 10],
                'features' => ['exports' => true],
            ],
        ]);

        $this->assertFalse(app(SubscriptionEntitlements::class)->allows($tenant->id, 'exports'));
    }

    public function test_inactive_subscription_blocks_quota_and_entitlement_reads(): void
    {
        [$tenant, , $subscription, $token] = $this->scenario();

        $subscription->forceFill(['status' => SubscriptionStatus::ReadOnly])->save();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $tenant->public_id)
            ->getJson('/api/v1/me/subscription')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_INACTIVE');

        try {
            app(ConsumeQuota::class)->handle($tenant->id, 'staff');
            $this->fail('An inactive subscription must reject quota consumption.');
        } catch (ApiException $exception) {
            $this->assertSame('SUBSCRIPTION_INACTIVE', $exception->errorCode);
            $this->assertSame(403, $exception->status);
        }
    }

    public function test_invalid_plan_features_are_rejected_when_subscription_starts(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SaasPlan::factory()->create([
            'features' => ['limits' => ['staff' => -1], 'features' => []],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(StartSubscription::class)->handle($tenant, $plan);
    }

    /**
     * @return array{Tenant, SaasPlan, TenantSubscription, string}
     */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        TenantMembership::factory()->for($tenant)->for($user)->create(['status' => 'active']);

        $plan = SaasPlan::factory()->create([
            'code' => 'starter',
            'revision' => 1,
            'features' => [
                'limits' => ['staff' => 1, 'branches' => 1],
                'features' => ['exports' => false],
            ],
        ]);

        $subscription = app(StartSubscription::class)->handle($tenant, $plan);
        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Subscription test device');

        return [$tenant, $plan, $subscription, $issued['token']->plainTextToken];
    }
}
