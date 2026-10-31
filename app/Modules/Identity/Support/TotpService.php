<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\Date;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

final class TotpService
{
    public function secret(): string
    {
        return TOTP::generate($this->clock(), 20)->getSecret();
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return TOTP::createFromSecret($secret, $this->clock())
            ->withLabel($user->email)
            ->withIssuer((string) config('identity.mfa.issuer'))
            ->getProvisioningUri();
    }

    public function verify(string $secret, string $code): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/\A\d{6}\z/', $normalized)) {
            return false;
        }

        return TOTP::createFromSecret($secret, $this->clock())
            ->verify($normalized, null, (int) config('identity.mfa.window'));
    }

    public function currentCode(string $secret): string
    {
        return TOTP::createFromSecret($secret, $this->clock())->now();
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface
        {
            public function now(): \DateTimeImmutable
            {
                return Date::now()->toImmutable();
            }
        };
    }
}
