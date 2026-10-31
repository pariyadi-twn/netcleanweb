<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\Password;

final class RequestPasswordReset
{
    public function handle(string $email): void
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->where('status', UserStatus::Active->value)
            ->first();

        if ($user === null) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
    }
}
