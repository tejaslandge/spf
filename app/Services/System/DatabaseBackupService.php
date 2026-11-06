<?php

namespace App\Services\System;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Executes real database backups and records their status in the settings
 * store. Nothing is reported as successful unless a dump file was written.
 */
final class DatabaseBackupService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->getBool('backup.enabled', false);
    }

    public function frequency(): string
    {
        return (string) $this->settings->get('backup.frequency', 'daily');
    }

    public function retentionDays(): int
    {
        return max(1, $this->settings->getInt('backup.retention_days', 30));
    }

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    public function isDue(?Carbon $now = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $lastRun = $this->settings->get('backup.last_run_at');

        if ($lastRun === null) {
            return true;
        }

        $lastRunAt = Carbon::parse((string) $lastRun);
        $now ??= now();

        return match ($this->frequency()) {
            'weekly' => $lastRunAt->copy()->addWeek()->lessThanOrEqualTo($now),
            'monthly' => $lastRunAt->copy()->addMonth()->lessThanOrEqualTo($now),
            default => $lastRunAt->copy()->addDay()->lessThanOrEqualTo($now),
        };
    }

    /**
     * Run the backup and persist the outcome.
     *
     * @return array{status: string, file: string|null, message: string|null}
     */
    public function run(): array
    {
        File::ensureDirectoryExists($this->directory());

        $driver = (string) config('database.default');
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $file = $this->directory().DIRECTORY_SEPARATOR.$driver.'-backup-'.now()->format('Ymd-His').'.'.$extension;

        try {
            match ($driver) {
                'sqlite' => $this->dumpSqlite($file),
                'pgsql' => $this->dumpPostgres($file),
                'mysql', 'mariadb' => $this->dumpMysql($file),
                default => throw new RuntimeException("Database driver [{$driver}] does not support automatic backups."),
            };
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            $this->settings->putMany([
                'backup.last_run_at' => now()->toIso8601String(),
                'backup.last_status' => 'failed',
                'backup.last_file' => null,
                'backup.last_error' => mb_substr($message, 0, 500),
            ], null, 'Scheduled backup attempt');

            return ['status' => 'failed', 'file' => null, 'message' => $message];
        }

        $this->prune();

        $this->settings->putMany([
            'backup.last_run_at' => now()->toIso8601String(),
            'backup.last_status' => 'success',
            'backup.last_file' => $file,
            'backup.last_error' => null,
        ], null, 'Scheduled backup');

        return ['status' => 'success', 'file' => $file, 'message' => null];
    }

    private function dumpSqlite(string $file): void
    {
        $database = (string) config('database.connections.sqlite.database');

        if ($database === ':memory:' || $database === '') {
            throw new RuntimeException('The in-memory SQLite database cannot be backed up.');
        }

        if (! File::exists($database)) {
            throw new RuntimeException("SQLite database file [{$database}] was not found.");
        }

        File::copy($database, $file);
    }

    private function dumpPostgres(string $file): void
    {
        $connection = config('database.connections.pgsql');

        $process = new Process([
            'pg_dump',
            '--no-owner',
            '--no-privileges',
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-d', (string) $connection['database'],
            '--file', $file,
        ], env: ['PGPASSWORD' => (string) $connection['password']], timeout: 900);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_dump failed: '.mb_substr($process->getErrorOutput(), 0, 400));
        }
    }

    private function dumpMysql(string $file): void
    {
        $connection = config('database.connections.'.config('database.default'));

        $process = new Process([
            'mysqldump',
            '-h', (string) $connection['host'],
            '-P', (string) $connection['port'],
            '-u', (string) $connection['username'],
            (string) $connection['database'],
            '--result-file='.$file,
        ], env: ['MYSQL_PWD' => (string) $connection['password']], timeout: 900);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.mb_substr($process->getErrorOutput(), 0, 400));
        }
    }

    private function prune(): void
    {
        $threshold = now()->subDays($this->retentionDays())->getTimestamp();

        foreach (File::glob($this->directory().DIRECTORY_SEPARATOR.'*-backup-*') as $file) {
            if (File::lastModified($file) < $threshold) {
                File::delete($file);
            }
        }
    }
}
