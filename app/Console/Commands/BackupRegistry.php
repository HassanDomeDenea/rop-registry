<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('registry:backup {--database-only : Skip the patient attachments}')]
#[Description('Create a backup archive of the registry database and attachments')]
class BackupRegistry extends Command
{
    public function handle(BackupService $backups): int
    {
        $name = $backups->create($this->option('database-only') ? 'database' : 'full');

        $this->components->info('Backup created: '.$backups->path($name));

        return self::SUCCESS;
    }
}
