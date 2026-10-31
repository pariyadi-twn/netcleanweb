<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpsHealthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_health_endpoint_reports_database_cache_and_private_storage(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', true)
            ->assertJsonPath('data.checks.cache', true)
            ->assertJsonPath('data.checks.storage', true);
    }

    public function test_metrics_endpoint_requires_token_and_exposes_operational_snapshot(): void
    {
        config(['ops.metrics_token' => 'metrics-secret']);
        Cache::flush();

        $this->getJson('/api/v1/ops/metrics')->assertUnauthorized();

        $this->getJson('/api/v1/ops/metrics', ['X-Metrics-Token' => 'metrics-secret'])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'requests' => ['requests', 'errors', 'latency_avg_ms'],
                    'database' => ['connections', 'active', 'waiting_locks', 'longest_transaction_seconds'],
                    'queue' => ['pending', 'oldest_age_seconds'],
                    'outbox' => ['without_receipt', 'oldest_age_seconds'],
                    'storage' => ['free_bytes', 'total_bytes', 'used_percent'],
                    'backup' => ['latest_age_seconds'],
                ],
            ]);
    }

    public function test_request_metrics_capture_successful_requests(): void
    {
        config(['ops.metrics_token' => 'metrics-secret']);
        Cache::flush();

        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertOk();

        $response = $this->getJson('/api/v1/ops/metrics', ['X-Metrics-Token' => 'metrics-secret'])
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, $response->json('data.requests.requests'));
        $this->assertSame(0, $response->json('data.requests.errors'));
    }

    public function test_health_check_command_requires_fresh_encrypted_backup(): void
    {
        $directory = storage_path('framework/testing/ops-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($directory);
        config(['ops.backup_path' => $directory]);

        $this->artisan('ops:health-check')->assertFailed();

        File::put($directory.'/backup.enc', 'encrypted');
        touch($directory.'/backup.enc', time());

        $this->artisan('ops:health-check')->assertSuccessful();

        File::deleteDirectory($directory);
    }

    public function test_backup_script_has_valid_shell_syntax(): void
    {
        exec('bash -n '.escapeshellarg(base_path('ops/backup.sh')), $output, $code);

        $this->assertSame(0, $code);
    }
}
