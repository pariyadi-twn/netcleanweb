<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\RecoveryCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class RegenerateMfaRecoveryCodes
{
    public function __construct(private readonly RecoveryCodes $recoveryCodes) {}

    /**
     * @return list<string>
     */
    public function handle(User $user, string $password): array
    {
        if (! $user->hasConfirmedMfa()) {
            throw ValidationException::withMessages(['mfa' => 'MFA is not enabled for this account.']);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'The provided password is incorrect.']);
        }

        $plainTextCodes = $this->recoveryCodes->generate((int) config('identity.mfa.recovery_code_count'));

        DB::transaction(function () use ($user, $plainTextCodes): void {
            $user->mfaRecoveryCodes()->delete();

            foreach ($plainTextCodes as $code) {
                $user->mfaRecoveryCodes()->create(['code_hash' => $this->recoveryCodes->hash($code)]);
            }
        });

        return $plainTextCodes;
    }
}
