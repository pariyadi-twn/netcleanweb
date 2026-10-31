<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\UserStatus;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class AuthenticateUser
{
    private const DUMMY_HASH = '$2y$12$kuKqWFF1H2UjFdvsZnqfe.nvpVso1iveCVNsnRpAUNLd5hJZRmvVK';

    public function __construct(private readonly VerifyMfaCode $verifyMfaCode) {}

    public function handle(string $email, string $password, ?string $mfaCode): User
    {
        $user = $this->credentials($email, $password);

        if (! $user->requiresMfa()) {
            return $user;
        }

        if (! $user->hasConfirmedMfa() || $mfaCode === null || $mfaCode === '' || ! $this->verifyMfaCode->handle($user, $mfaCode)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        return $user;
    }

    public function credentials(string $email, string $password): User
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
        $passwordMatches = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordMatches || $user->status !== UserStatus::Active) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        return $user;
    }
}
