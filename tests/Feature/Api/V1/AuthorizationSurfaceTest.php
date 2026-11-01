<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationSurfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_only_endpoints_reject_regular_users(): void
    {
        $user = User::factory()->create();
        $token = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Authorization device')['token']->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/platform/tenants')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/platform/plans', [
            'code' => 'forbidden',
            'revision' => 1,
            'name' => 'Forbidden',
            'base_price_rupiah' => 1,
            'features' => ['limits' => [], 'features' => []],
        ])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/platform/notifications/'.Str::uuid().'/reconcile', [
            'status' => 'failed',
        ])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/support/received-grants')->assertForbidden();
    }
}
