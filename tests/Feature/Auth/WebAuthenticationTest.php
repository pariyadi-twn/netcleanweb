<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesIdentityUsers;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use CreatesIdentityUsers;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_authenticates_the_user_and_rotates_the_session_id(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->get('/login')->assertOk();
        $previousSessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_logout_invalidates_the_web_session(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->actingAs($user);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $user = User::factory()->disabled()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_owner_with_mfa_must_complete_the_challenge_before_session_login(): void
    {
        $owner = $this->createOwner(['password' => 'secret-password']);
        $this->enableMfa($owner);

        $this->post('/login', [
            'email' => $owner->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('mfa.challenge'));

        $this->assertGuest();

        $this->post('/mfa-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/mfa-challenge', ['code' => $this->currentMfaCode($owner)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_without_mfa_is_sent_to_setup_and_can_confirm_it(): void
    {
        $owner = $this->createOwner(['password' => 'secret-password']);

        $this->post('/login', [
            'email' => $owner->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('mfa.setup'));

        $this->assertAuthenticatedAs($owner);
        $this->get('/dashboard')->assertRedirect(route('mfa.setup'));

        $this->get('/mfa-setup')->assertOk()->assertSee('Aktifkan MFA');

        $owner->refresh();
        $code = app(TotpService::class)->currentCode((string) $owner->mfa_secret_ref);

        $this->post('/mfa-setup', ['code' => $code])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('recovery_codes');

        $this->assertNotNull($owner->refresh()->mfa_confirmed_at);
        $this->get('/dashboard')->assertRedirect(route('tenants.select'));
        $this->get('/tenants/select')->assertOk()->assertSee('Recovery codes');
    }

    public function test_secure_and_http_only_session_cookie_flags_are_applied(): void
    {
        config(['session.secure' => true]);

        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }
}
