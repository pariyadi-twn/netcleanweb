<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class ResetPassword
{
    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function handle(array $credentials): string
    {
        return Password::broker()->reset($credentials, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                $user->devices()->update(['revoked_at' => now()]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
        });
    }
}
