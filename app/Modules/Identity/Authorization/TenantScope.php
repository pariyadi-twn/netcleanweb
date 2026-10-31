<?php

namespace App\Modules\Identity\Authorization;

use App\Models\User;
use App\Modules\Identity\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;

final class TenantScope
{
    public function identity(User $user, Closure $callback, ?string $connection = null): mixed
    {
        return $this->run($connection, [
            'app.tenant_id' => '',
            'app.user_id' => (string) $user->id,
            'app.identity_bootstrap' => 'on',
        ], $callback);
    }

    public function forTenant(Tenant $tenant, User $user, Closure $callback, ?string $connection = null): mixed
    {
        return $this->forTenantId($tenant->id, $user->id, $callback, $connection);
    }

    public function forTenantId(int $tenantId, int $userId, Closure $callback, ?string $connection = null): mixed
    {
        return $this->run($connection, [
            'app.tenant_id' => (string) $tenantId,
            'app.user_id' => (string) $userId,
            'app.identity_bootstrap' => 'off',
        ], $callback);
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function run(?string $connectionName, array $settings, Closure $callback): mixed
    {
        $connectionName ??= DB::getDefaultConnection();
        $previousConnection = DB::getDefaultConnection();

        DB::setDefaultConnection($connectionName);

        try {
            return DB::connection($connectionName)->transaction(function () use ($connectionName, $settings, $callback): mixed {
                foreach ($settings as $key => $value) {
                    DB::connection($connectionName)->select('SELECT set_config(?, ?, true)', [$key, $value]);
                }

                return $callback();
            });
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }
}
