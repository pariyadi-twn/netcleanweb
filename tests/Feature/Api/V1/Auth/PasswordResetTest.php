<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reset_link_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $known = $this->postJson('/api/v1/auth/password-reset-links', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/password-reset-links', ['email' => 'missing@example.com']);

        $known->assertStatus(202)->assertJsonPath('data.status', 'accepted');
        $unknown->assertStatus(202)->assertJsonPath('data.status', 'accepted');
        $this->assertSame($known->json('data'), $unknown->json('data'));

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_reset_changes_password_and_revokes_existing_device_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'old-password-123']);
        $issued = app(IssueDeviceToken::class)->handle($user, (string) Str::uuid(), 'Phone');

        $this->postJson('/api/v1/auth/password-reset-links', ['email' => $user->email])->assertStatus(202);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/password-resets', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()->assertJsonPath('data.status', 'password_reset');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertNotNull($issued['device']->refresh()->revoked_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_invalid_or_weak_reset_requests_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password-resets', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['field_errors' => ['email']]]);

        $this->postJson('/api/v1/auth/password-resets', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['field_errors' => ['password']]]);
    }
}
