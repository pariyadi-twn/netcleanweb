<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\Device;
use Illuminate\Support\Facades\DB;

final class RevokeDeviceToken
{
    public function handle(User $user, Device $device): void
    {
        abort_unless($device->user_id === $user->id, 404);

        DB::transaction(function () use ($device): void {
            $device->tokens()->delete();
            $device->forceFill(['revoked_at' => now()])->save();
        });
    }
}
