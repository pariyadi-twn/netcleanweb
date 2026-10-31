<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\RecoveryCodes;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmMfaEnrollment
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly RecoveryCodes $recoveryCodes,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(User $user, string $code): array
    {
        $secret = $user->mfa_secret_ref;

        if ($secret === null) {
            throw ValidationException::withMessages(['code' => 'Start MFA enrollment before confirming it.']);
        }

        if (! $this->totp->verify($secret, $code)) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        $plainTextCodes = $this->recoveryCodes->generate((int) config('identity.mfa.recovery_code_count'));

        DB::transaction(function () use ($user, $plainTextCodes): void {
            $user->forceFill(['mfa_confirmed_at' => now()])->save();
            $user->mfaRecoveryCodes()->delete();

            foreach ($plainTextCodes as $code) {
                $user->mfaRecoveryCodes()->create(['code_hash' => $this->recoveryCodes->hash($code)]);
            }
        });

        return $plainTextCodes;
    }
}
