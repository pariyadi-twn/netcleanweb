<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

#[Signature('project:prepare')]
#[Description('Prepare isolated local and testing environments without changing existing credentials')]
class PrepareProjectEnvironment extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        if (! $this->supportsPrivateFiles($files)) {
            $this->components->error('The filesystem does not enforce private file permissions (0600). Fix the filesystem mount before preparing credentials.');

            return self::FAILURE;
        }

        $infrastructureFile = base_path('.env.infrastructure');

        if (! $files->exists($infrastructureFile)) {
            $values = [
                'POSTGRES_PASSWORD' => bin2hex(random_bytes(32)),
                'DB_MIGRATION_PASSWORD' => bin2hex(random_bytes(32)),
                'DB_RUNTIME_PASSWORD' => bin2hex(random_bytes(32)),
                'S3_ACCESS_KEY' => bin2hex(random_bytes(12)),
                'S3_SECRET_KEY' => bin2hex(random_bytes(32)),
                'POSTGRES_PORT' => '15432',
                'REDIS_CACHE_PORT' => '16379',
                'REDIS_QUEUE_PORT' => '16380',
                'S3_PORT' => '18333',
                'STORAGE_UID' => (string) fileowner(base_path()),
                'STORAGE_GID' => (string) filegroup(base_path()),
            ];

            $this->writeEnvironment($files, $infrastructureFile, $values);
        }

        if (! $this->protect($files, $infrastructureFile, 0600)) {
            $this->components->error('Infrastructure credentials could not be protected. Check file ownership and permissions.');

            return self::FAILURE;
        }

        $values = Dotenv::parse($files->get($infrastructureFile));

        foreach (['POSTGRES_PASSWORD', 'DB_MIGRATION_PASSWORD', 'DB_RUNTIME_PASSWORD', 'S3_ACCESS_KEY', 'S3_SECRET_KEY', 'POSTGRES_PORT', 'REDIS_CACHE_PORT', 'REDIS_QUEUE_PORT', 'S3_PORT'] as $key) {
            if (empty($values[$key]) || ! preg_match('/\A[a-zA-Z0-9]+\z/', $values[$key])) {
                $this->components->error('Infrastructure configuration is incomplete or contains an invalid value for '.$key.'.');

                return self::FAILURE;
            }
        }

        foreach (['local', 'testing'] as $environment) {
            $path = base_path('.env.'.$environment);

            if (! $files->exists($path)) {
                $this->writeEnvironment($files, $path, $this->applicationEnvironment($environment, $values));
            }

            if (! $this->protect($files, $path, 0600)) {
                $this->components->error('Application environment could not be protected. Check file ownership and permissions.');

                return self::FAILURE;
            }
        }

        $directory = storage_path('app/infrastructure');
        $objectsDirectory = $directory.'/objects';
        $files->ensureDirectoryExists($directory, 0700);
        $files->ensureDirectoryExists($objectsDirectory, 0700);

        if (! $this->protect($files, $directory, 0700) || ! $this->protect($files, $objectsDirectory, 0700)) {
            $this->components->error('The infrastructure directories could not be protected. Check their ownership and permissions.');

            return self::FAILURE;
        }

        if (! $files->exists($directory.'/s3.json')) {
            $this->writePrivateFile($files, $directory.'/s3.json', json_encode([
                'identities' => [[
                    'name' => 'netclean-local',
                    'credentials' => [[
                        'accessKey' => $values['S3_ACCESS_KEY'],
                        'secretKey' => $values['S3_SECRET_KEY'],
                    ]],
                    'actions' => ['Admin', 'Read', 'Write', 'List', 'Tagging'],
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
        }

        if (! $this->protect($files, $directory.'/s3.json', 0600)) {
            $this->components->error('Storage credentials could not be protected. Check file ownership and permissions.');

            return self::FAILURE;
        }

        $this->components->info('Local infrastructure, local application, and testing configuration are ready. Existing files were preserved.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnvironment(Filesystem $files, string $path, array $values): void
    {
        $contents = '';

        foreach ($values as $key => $value) {
            $contents .= $key.'='.$value."\n";
        }

        $this->writePrivateFile($files, $path, $contents);
    }

    private function writePrivateFile(Filesystem $files, string $path, string $contents): void
    {
        $files->put($path, '');

        if (! $this->protect($files, $path, 0600)) {
            $files->delete($path);

            throw new RuntimeException('Credentials were not written because private file permissions could not be enforced.');
        }

        $files->put($path, $contents);
    }

    private function supportsPrivateFiles(Filesystem $files): bool
    {
        $probe = tempnam(base_path(), '.netclean-permissions-');

        if ($probe === false) {
            return false;
        }

        try {
            return dirname($probe) === realpath(base_path()) && $this->protect($files, $probe, 0600);
        } finally {
            $files->delete($probe);
        }
    }

    private function protect(Filesystem $files, string $path, int $mode): bool
    {
        try {
            $files->chmod($path, $mode);
            clearstatcache(true, $path);

            return $files->chmod($path) === sprintf('%04o', $mode);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function applicationEnvironment(string $environment, array $values): array
    {
        $isTesting = $environment === 'testing';

        return [
            'APP_NAME' => 'NetClean',
            'APP_ENV' => $environment,
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => $isTesting ? 'false' : 'true',
            'APP_URL' => 'http://localhost:8000',
            'APP_LOCALE' => 'id',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'id_ID',
            'LOG_CHANNEL' => 'single',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $values['POSTGRES_PORT'],
            'DB_DATABASE' => $isTesting ? 'netclean_test' : 'netclean',
            'DB_USERNAME' => $isTesting ? 'netclean_migrator' : 'netclean_runtime',
            'DB_PASSWORD' => $isTesting ? $values['DB_MIGRATION_PASSWORD'] : $values['DB_RUNTIME_PASSWORD'],
            'DB_MIGRATION_USERNAME' => 'netclean_migrator',
            'DB_MIGRATION_PASSWORD' => $values['DB_MIGRATION_PASSWORD'],
            'DB_RUNTIME_USERNAME' => 'netclean_runtime',
            'DB_RUNTIME_PASSWORD' => $values['DB_RUNTIME_PASSWORD'],
            'REDIS_CLIENT' => 'predis',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => $values['REDIS_QUEUE_PORT'],
            'REDIS_CACHE_HOST' => '127.0.0.1',
            'REDIS_CACHE_PORT' => $values['REDIS_CACHE_PORT'],
            'REDIS_QUEUE_HOST' => '127.0.0.1',
            'REDIS_QUEUE_PORT' => $values['REDIS_QUEUE_PORT'],
            'REDIS_PREFIX' => 'netclean-'.$environment.'-',
            'SESSION_DRIVER' => $isTesting ? 'array' : 'redis',
            'SESSION_CONNECTION' => 'cache',
            'SESSION_ENCRYPT' => 'true',
            'CACHE_STORE' => $isTesting ? 'array' : 'redis',
            'QUEUE_CONNECTION' => $isTesting ? 'sync' : 'redis',
            'IDENTITY_TOKEN_TTL_MINUTES' => '720',
            'IDENTITY_MFA_ISSUER' => 'NetClean',
            'MAIL_MAILER' => $isTesting ? 'array' : 'log',
            'FILESYSTEM_DISK' => 's3',
            'AWS_ACCESS_KEY_ID' => $values['S3_ACCESS_KEY'],
            'AWS_SECRET_ACCESS_KEY' => $values['S3_SECRET_KEY'],
            'AWS_DEFAULT_REGION' => 'us-east-1',
            'AWS_BUCKET' => $isTesting ? 'netclean-test' : 'netclean-private',
            'AWS_ENDPOINT' => 'http://127.0.0.1:'.$values['S3_PORT'],
            'AWS_USE_PATH_STYLE_ENDPOINT' => 'true',
        ];
    }
}
