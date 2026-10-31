<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\RecoveryCodes;
use App\Modules\Identity\Support\TotpService;
use Illuminate\Support\Facades\Cache;

final class VerifyMfaCode
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly RecoveryCodes $recoveryCodes,
    ) {}

    public function handle(User $user, string $code): bool
    {
        $code = trim($code);
        $secret = $user->mfa_secret_ref;

        if ($secret !== null && preg_match('/\A\d{6}\z/', $code) === 1 && $this->totp->verify($secret, $code)) {
            return Cache::add(
                'mfa:totp:'.$user->id.':'.hash('sha256', $code),
                true,
                now()->addSeconds(90),
            );
        }

        $recovery = $user->mfaRecoveryCodes()
            ->where('code_hash', $this->recoveryCodes->hash($code))
            ->whereNull('used_at')
            ->first();

        if ($recovery === null) {
            return false;
        }

        return $recovery->forceFill(['used_at' => now()])->save();
    }
}
