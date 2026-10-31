<?php

namespace App\Modules\Identity\Authorization;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class ProvisioningScope
{
    public function run(User $user, Closure $callback, ?string $connection = null): mixed
    {
        $connection ??= DB::getDefaultConnection();
        $previousConnection = DB::getDefaultConnection();

        DB::setDefaultConnection($connection);

        try {
            return DB::connection($connection)->transaction(function () use ($connection, $user, $callback): mixed {
                foreach ([
                    'app.tenant_id' => '',
                    'app.user_id' => (string) $user->id,
                    'app.identity_bootstrap' => 'off',
                ] as $key => $value) {
                    DB::connection($connection)->select('SELECT set_config(?, ?, true)', [$key, $value]);
                }

                return $callback();
            });
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }

    public function activateTenant(int $tenantId): void
    {
        DB::select("SELECT set_config('app.tenant_id', ?, true)", [(string) $tenantId]);
    }
}
