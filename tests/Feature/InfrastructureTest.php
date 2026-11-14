<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_postgresql_18_uses_an_isolated_test_database_and_separate_non_privileged_roles(): void
    {
        $migration = DB::selectOne('SELECT current_database() AS database, current_user AS role, current_setting(?)::int AS version', ['server_version_num']);
        $runtime = DB::connection('pgsql_runtime')->selectOne('SELECT current_database() AS database, current_user AS role, rolsuper, rolbypassrls, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = current_user');

        $this->assertSame('netclean_test', $migration->database);
        $this->assertSame('netclean_migrator', $migration->role);
        $this->assertSame(18, intdiv($migration->version, 10000));
        $this->assertSame('netclean_test', $runtime->database);
        $this->assertSame('netclean_runtime', $runtime->role);
        $this->assertFalse($runtime->rolsuper);
        $this->assertFalse($runtime->rolbypassrls);
        $this->assertFalse($runtime->rolcreatedb);
        $this->assertFalse($runtime->rolcreaterole);
    }

    public function test_runtime_role_cannot_create_tables(): void
    {
        $connection = DB::connection('pgsql_runtime');
        $connection->beginTransaction();

        try {
            $connection->statement('CREATE TABLE runtime_permission_probe (id bigint)');
            $this->fail('Runtime role must not be able to create application tables.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->errorInfo[0]);
        } finally {
            $connection->rollBack();
        }
    }

    public function test_queue_and_cache_use_independent_redis_servers_with_non_eviction_for_jobs(): void
    {
        $key = 'infrastructure-probe:'.Str::uuid();
        $queue = Redis::connection('queue');
        $cache = Redis::connection('cache');

        try {
            $queue->set($key, 'queued-command');
            $cache->set($key, 'cached-value');

            $this->assertSame('queued-command', $queue->get($key));
            $this->assertSame('cached-value', $cache->get($key));
            $this->assertContains('noeviction', $queue->config('GET', 'maxmemory-policy'));
        } finally {
            $queue->del($key);
            $cache->del($key);
        }
    }

    public function test_private_s3_objects_can_be_read_with_credentials_but_anonymous_access_returns_403(): void
    {
        $this->assertSame('netclean-test', config('filesystems.disks.s3.bucket'));
        $path = 'infrastructure-probes/'.Str::uuid().'.txt';
        $disk = Storage::disk('s3');
        $uploaded = false;

        try {
            $this->retryStorage(fn () => $disk->put($path, 'synthetic-private-proof'));
            $uploaded = true;

            $this->assertSame('synthetic-private-proof', $this->retryStorage(fn () => $disk->get($path)));
            $response = Http::connectTimeout(3)->timeout(5)->get(rtrim(config('filesystems.disks.s3.endpoint'), '/').'/netclean-test/'.$path);
            $this->assertSame(403, $response->status());
        } finally {
            if ($uploaded) {
                $this->retryStorage(fn () => $disk->delete($path));
            }
        }
    }

    private function retryStorage(callable $operation, int $attempts = 10): mixed
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $error) {
                $lastError = $error;
                usleep(500_000);
            }
        }

        throw $lastError;
    }
}
