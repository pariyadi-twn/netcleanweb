<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PrepareProjectEnvironmentTest extends TestCase
{
    public function test_credentials_are_not_written_when_chmod_succeeds_but_actual_permissions_remain_0777(): void
    {
        $this->partialMock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('chmod')->with(Mockery::type('string'), 0600)->andReturn(true);
            $mock->shouldReceive('chmod')->with(Mockery::type('string'))->andReturn('0777');
            $mock->shouldNotReceive('put');
        });

        $this->artisan('project:prepare')
            ->expectsOutputToContain('The filesystem does not enforce private file permissions (0600).')
            ->assertFailed();
    }

    public function test_private_environments_are_created_and_existing_credentials_are_preserved(): void
    {
        $files = new Filesystem;
        $directory = '/tmp/opencode/netclean-prepare-test-'.bin2hex(random_bytes(8));
        $files->ensureDirectoryExists($directory, 0700);
        $originalBasePath = base_path();
        Artisan::all();
        $this->app->setBasePath($directory);

        try {
            $this->artisan('project:prepare')->assertSuccessful();
            $paths = ['.env.infrastructure', '.env.local', '.env.testing', 'storage/app/infrastructure/s3.json'];
            $hashes = [];

            foreach ($paths as $path) {
                $this->assertSame('0600', $files->chmod($directory.'/'.$path));
                $hashes[$path] = hash_file('sha256', $directory.'/'.$path);
            }

            foreach (['storage/app/infrastructure', 'storage/app/infrastructure/objects'] as $path) {
                $this->assertSame('0700', $files->chmod($directory.'/'.$path));
            }

            $this->artisan('project:prepare')->assertSuccessful();

            foreach ($paths as $path) {
                $this->assertSame($hashes[$path], hash_file('sha256', $directory.'/'.$path));
            }

            $this->assertFileDoesNotExist($directory.'/.env');
        } finally {
            $this->app->setBasePath($originalBasePath);
            $files->deleteDirectory($directory);
        }
    }
}
