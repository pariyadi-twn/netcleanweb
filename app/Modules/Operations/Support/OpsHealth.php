<?php

namespace App\Modules\Operations\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class OpsHealth
{
    /**
     * @return array{status: string, checks: array<string, bool>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->database(),
            'storage' => $this->storage(),
            'cache' => $this->cache(),
        ];

        return [
            'status' => in_array(false, $checks, true) ? 'unhealthy' : 'ok',
            'checks' => $checks,
        ];
    }

    private function database(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storage(): bool
    {
        try {
            $disk = Storage::disk((string) config('uploads.disk', 'local'));
            $path = 'health/'.bin2hex(random_bytes(8));
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    private function cache(): bool
    {
        try {
            if (config('cache.default') === 'redis') {
                Redis::connection('cache')->ping();
            } else {
                Cache::put('ops:health', 'ok', 10);
                $ok = Cache::get('ops:health') === 'ok';
                Cache::forget('ops:health');

                return $ok;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
