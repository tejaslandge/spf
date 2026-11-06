<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'system:backup-database
        {--if-due : Only run when the configured backup schedule says a backup is due}
        {--force : Run even when scheduled backups are disabled}';

    protected $description = 'Create a database backup and record its status in the settings store';

    public function handle(DatabaseBackupService $backups): int
    {
        if (! $this->option('force') && ! $this->option('if-due') && ! $backups->isEnabled()) {
            $this->warn('Scheduled backups are disabled. Use --force to run a one-off backup.');

            return self::SUCCESS;
        }

        if ($this->option('if-due') && ! $backups->isDue()) {
            $this->info('No backup is due at this time.');

            return self::SUCCESS;
        }

        $result = $backups->run();

        if ($result['status'] === 'success') {
            $this->info('Backup completed: '.$result['file']);

            return self::SUCCESS;
        }

        $this->error('Backup failed: '.$result['message']);

        return self::FAILURE;
    }
}
