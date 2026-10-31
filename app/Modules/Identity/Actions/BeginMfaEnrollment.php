<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Validation\ValidationException;

final class BeginMfaEnrollment
{
    public function __construct(private readonly TotpService $totp) {}

    /**
     * @return array{secret: string, provisioning_uri: string}
     */
    public function handle(User $user): array
    {
        if ($user->hasConfirmedMfa()) {
            throw ValidationException::withMessages(['mfa' => 'MFA is already enabled for this account.']);
        }

        $secret = $user->mfa_secret_ref ?? $this->totp->secret();

        $user->forceFill([
            'mfa_secret_ref' => $secret,
            'mfa_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($user, $secret),
        ];
    }
}
